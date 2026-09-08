<?php

namespace App\Application\Payments\Webhooks;

use App\Application\Payments\Gateways\PaymentGateway;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\MercadoPagoAccounts\Repositories\MercadoPagoAccountRepository;
use App\Domain\Payments\Repositories\PaymentRepository;
use App\Domain\Payments\Services\ReservationPaymentPolicy;
use App\Domain\Reservations\Events\ReservationConfirmed;
use App\Domain\Reservations\Repositories\ReservationRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ProcessMercadoPagoWebhookHandler
{
    public function __construct(
        private readonly PaymentGateway $paymentGateway,
        private readonly PaymentRepository $paymentRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationPaymentPolicy $paymentPolicy,
        private readonly MercadoPagoAccountRepository $mercadoPagoAccounts,
    ) {}

    public function handle(
        ProcessMercadoPagoWebhookCommand $command
    ): void {
        /*
        |--------------------------------------------------------------------------
        | 1. Resolver vendedor
        |--------------------------------------------------------------------------
        |
        | user_id del webhook es el usuario/vendedor de Mercado Pago.
        |
        */

        $mercadoPagoAccount = $this->mercadoPagoAccounts
            ->findByMercadoPagoUserId(
                $command->mercadoPagoUserId
            );

        if ($mercadoPagoAccount === null) {
            throw new RuntimeException(
                'No se encontró la cuenta de Mercado Pago del vendedor.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Consultar el Payment real
        |--------------------------------------------------------------------------
        |
        | No confiamos en status, monto ni external_reference enviados
        | directamente por la notificación.
        |
        | Consultamos Mercado Pago usando el Access Token del vendedor.
        |
        */

        $gatewayPayment = $this->paymentGateway
            ->getPayment(
                mercadoPagoAccountId: $mercadoPagoAccount->getId(),

                providerPaymentId: $command->providerPaymentId,
            );

        if (
            $gatewayPayment->externalReference === null
        ) {
            throw new RuntimeException(
                'El pago no posee external_reference.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Payment local
        |--------------------------------------------------------------------------
        */

        $payment = $this->paymentRepository
            ->findByExternalReference(
                $gatewayPayment->externalReference
            );

        if ($payment === null) {
            throw new RuntimeException(
                'No se encontró el pago asociado.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Verificar vendedor
        |--------------------------------------------------------------------------
        |
        | Muy importante.
        |
        | Aunque el external_reference exista, el pago local tiene que haber
        | sido creado usando exactamente la misma MercadoPagoAccount.
        |
        */

        if (
            $payment->getMercadoPagoAccountId()
            !== $mercadoPagoAccount->getId()
        ) {
            throw new RuntimeException(
                'La cuenta de Mercado Pago del webhook no coincide con la cuenta del pago.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Verificar provider payment id
        |--------------------------------------------------------------------------
        */

        if (
            $gatewayPayment->providerPaymentId
            !== $command->providerPaymentId
        ) {
            throw new RuntimeException(
                'El identificador del pago no coincide.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Moneda
        |--------------------------------------------------------------------------
        */

        if (
            $gatewayPayment->currency !== 'ARS'
        ) {
            throw new RuntimeException(
                'La moneda del pago no es válida.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Monto
        |--------------------------------------------------------------------------
        */

        if (
            bccomp(
                $gatewayPayment->amount,
                $payment->getAmount(),
                2
            ) !== 0
        ) {
            throw new RuntimeException(
                'El monto recibido no coincide con el monto esperado.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Procesamiento transaccional
        |--------------------------------------------------------------------------
        */

        DB::transaction(
            function () use (
                $gatewayPayment,
                $payment
            ): void {
                /*
                |--------------------------------------------------------------------------
                | Idempotencia
                |--------------------------------------------------------------------------
                */

                if (
                    $payment->getStatus()
                    === PaymentStatus::APPROVED
                ) {
                    return;
                }

                $payment->setProviderPaymentId(
                    $gatewayPayment->providerPaymentId
                );

                switch ($gatewayPayment->status) {
                    case 'approved':
                        $paidAt =
                            $gatewayPayment->paidAt !== null
                            ? new DateTimeImmutable(
                                $gatewayPayment->paidAt
                            )
                            : new DateTimeImmutable();

                        $payment->markApproved(
                            providerPaymentId: $gatewayPayment->providerPaymentId,

                            paidAt: $paidAt,
                        );

                        $this->paymentRepository
                            ->update(
                                $payment
                            );

                        $this->tryConfirmReservation(
                            $payment->getReservationId()
                        );

                        break;

                    case 'rejected':
                        $payment->markRejected();

                        $this->paymentRepository
                            ->update(
                                $payment
                            );

                        break;

                    case 'cancelled':
                        $payment->markCancelled();

                        $this->paymentRepository
                            ->update(
                                $payment
                            );

                        break;

                    case 'refunded':
                        $payment->markRefunded();

                        $this->paymentRepository
                            ->update(
                                $payment
                            );

                        break;

                    /*
                    |--------------------------------------------------------------------------
                    | pending / in_process / etc.
                    |--------------------------------------------------------------------------
                    |
                    | Sigue PENDING.
                    |
                    */

                    default:
                        break;
                }
            },
            3
        );
    }

    private function tryConfirmReservation(
        int $reservationId
    ): void {
        $reservation = $this->reservationRepository
            ->findByIdForUpdate(
                $reservationId
            );

        if ($reservation === null) {
            return;
        }

        $approvedAmount =
            $this->paymentRepository
            ->sumApprovedByReservation(
                $reservationId
            );

        if (
            ! $this->paymentPolicy
                ->isDepositCovered(
                    totalPrice: $reservation->getTotalPrice(),

                    approvedAmount: $approvedAmount,
                )
        ) {
            return;
        }

        if (
            ! $reservation->confirmFromPayment(
                new DateTimeImmutable()
            )
        ) {
            return;
        }

        $updated =
            $this->reservationRepository
            ->update(
                $reservation
            );

        ReservationConfirmed::dispatch(
            $updated->getId()
        );
    }
}
