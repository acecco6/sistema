<?php

namespace App\Application\Payments\Webhooks;

use App\Application\Payments\Gateways\PaymentGateway;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Repositories\PaymentRepository;
use App\Domain\Payments\Services\ReservationPaymentPolicy;
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
    ) {}

    public function handle(
        ProcessMercadoPagoWebhookCommand $command
    ): void {
        /*
         * No confiamos en los datos enviados directamente
         * por el webhook.
         *
         * Consultamos el pago real a Mercado Pago.
         */
        $gatewayPayment = $this->paymentGateway->getPayment(
            $command->providerPaymentId
        );

        if ($gatewayPayment->externalReference === null) {
            throw new RuntimeException(
                'El pago no posee external_reference.'
            );
        }

        $payment = $this->paymentRepository
            ->findByExternalReference(
                $gatewayPayment->externalReference
            );

        /*
         * No procesamos pagos que no pertenecen
         * a nuestro sistema.
         */
        if ($payment === null) {
            throw new RuntimeException(
                'No se encontró el pago asociado.'
            );
        }

        /*
         * Además del external_reference verificamos
         * el ID real de Mercado Pago.
         */
        if ($gatewayPayment->providerPaymentId !== $command->providerPaymentId) {
            throw new RuntimeException('El identificador del pago no coincide.');
        }

        if ($gatewayPayment->currency !== 'ARS') {
            throw new RuntimeException('La moneda del pago no es válida.');
        }

        if (bccomp($gatewayPayment->amount, $payment->getAmount(), 2) !== 0) {
            throw new RuntimeException('El monto recibido no coincide con el monto esperado.');
        }

        DB::transaction(function () use ($gatewayPayment, $payment): void {
            /*
             * Idempotencia:
             *
             * Mercado Pago puede mandar el mismo webhook
             * varias veces.
             */
            if ($payment->getStatus() === PaymentStatus::APPROVED) {
                return;
            }

            switch ($gatewayPayment->status) {
                case 'approved':
                    $paidAt = $gatewayPayment->paidAt !== null
                        ? new DateTimeImmutable($gatewayPayment->paidAt)
                        : new DateTimeImmutable();

                    $payment->markApproved(
                        providerPaymentId: $gatewayPayment->providerPaymentId,
                        paidAt: $paidAt,
                    );

                    $this->paymentRepository->update($payment);

                    $this->tryConfirmReservation(
                        $payment->getReservationId()
                    );

                    break;

                case 'rejected':
                    $payment->markRejected();

                    $this->paymentRepository->update($payment);
                    break;

                case 'cancelled':
                    $payment->markCancelled();

                    $this->paymentRepository->update($payment);
                    break;

                case 'refunded':
                    $payment->markRefunded();

                    $this->paymentRepository->update($payment);
                    break;

                /*
                 * pending / in_process / etc.
                 *
                 * Nuestro Payment continúa PENDING.
                 */
                default:
                    break;
            }
        }, 3);
    }

    private function tryConfirmReservation(int $reservationId): void
    {
        /*
         * Lock importante:
         * evita que el job de expiración y el webhook
         * modifiquen la misma reserva simultáneamente.
         */
        $reservation = $this->reservationRepository->findByIdForUpdate($reservationId);

        if ($reservation === null) {
            return;
        }

        $approvedAmount = $this->paymentRepository->sumApprovedByReservation($reservationId);

        if (! $this->paymentPolicy->isDepositCovered(totalPrice: $reservation->getTotalPrice(), approvedAmount: $approvedAmount)) {
            return;
        }

        /*
         * confirmFromPayment también verifica que
         * la reserva siga PENDING y no haya vencido.
         */
        if (! $reservation->confirmFromPayment(new DateTimeImmutable())) {
            return;
        }

        $this->reservationRepository->update($reservation);
    }
}
