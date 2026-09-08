<?php

namespace App\Application\Payments\CreateCheckout;

use App\Application\Payments\DTOs\PaymentCheckoutDto;
use App\Application\Payments\Gateways\PaymentGateway;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Courts\Exceptions\CourtNotFoundException;
use App\Domain\Courts\Repositories\CourtRepository;
use App\Domain\Payments\Entities\Payment;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Repositories\PaymentRepository;
use App\Domain\Payments\Services\ReservationPaymentPolicy;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class CreatePaymentCheckoutHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly ReservationPaymentPolicy $paymentPolicy,
        private readonly PaymentGateway $paymentGateway,
        private readonly CourtRepository $courtRepository,
        private readonly BranchRepository $branchRepository,
    ) {}

    public function __invoke(
        CreatePaymentCheckoutCommand $command
    ): PaymentCheckoutDto {
        /*
        |--------------------------------------------------------------------------
        | 1. Buscar Reservation
        |--------------------------------------------------------------------------
        */

        $reservation = $this->reservationRepository
            ->findById($command->reservationId);

        if ($reservation === null) {
            throw new ReservationNotFoundException();
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Validar estado
        |--------------------------------------------------------------------------
        */

        if (
            $reservation->getStatus()
            !== ReservationStatus::PENDING
        ) {
            throw new RuntimeException(
                'Solo se puede generar un checkout para una reserva pendiente.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Validar expiración
        |--------------------------------------------------------------------------
        */

        $expiresAt = $reservation->getExpiresAt();

        if ($expiresAt === null) {
            throw new RuntimeException(
                'La reserva pendiente no posee fecha de expiración.'
            );
        }

        if ($expiresAt <= new DateTimeImmutable()) {
            throw new RuntimeException(
                'La reserva ya expiró.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Payment PENDING existente
        |--------------------------------------------------------------------------
        |
        | Si ya generamos un checkout para esta reserva no generamos otra
        | Preference en Mercado Pago.
        |
        */

        $existingPayment = $this->paymentRepository
            ->findPendingByReservation(
                $reservation->getId()
            );

        if ($existingPayment !== null) {
            return new PaymentCheckoutDto(
                paymentId: $existingPayment->getId(),
                amount: $existingPayment->getAmount(),
                percentage: $this->paymentPolicy->percentage(),
                checkoutUrl: $existingPayment->getCheckoutUrl(),
                expiresAt: $expiresAt->format(
                    'Y-m-d H:i:s'
                ),
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Resolver Court
        |--------------------------------------------------------------------------
        |
        | Reservation no tiene club_id directamente.
        |
        | Reservation -> Court -> Branch -> Club.
        |
        */

        $court = $this->courtRepository
            ->findById(
                $reservation->getCourtId()
            );

        if ($court === null) {
            throw new CourtNotFoundException();
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Resolver Branch
        |--------------------------------------------------------------------------
        */

        $branch = $this->branchRepository
            ->findById(
                $court->getBranchId()
            );

        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Resolver Club
        |--------------------------------------------------------------------------
        */

        $clubId = $branch->getClubId();

        /*
        |--------------------------------------------------------------------------
        | 8. Calcular seña
        |--------------------------------------------------------------------------
        */

        $amount = $this->paymentPolicy
            ->requiredDeposit(
                $reservation->getTotalPrice()
            );

        /*
        |--------------------------------------------------------------------------
        | 9. External reference
        |--------------------------------------------------------------------------
        */

        $externalReference = sprintf(
            'PAY-%s',
            Uuid::uuid4()->toString()
        );

        /*
        |--------------------------------------------------------------------------
        | 10. Crear Checkout con la cuenta MP del Club
        |--------------------------------------------------------------------------
        |
        | A partir de ahora PaymentGateway recibe clubId.
        |
        | MercadoPagoPaymentGateway:
        |
        | clubId
        |   ↓
        | mercado_pago_accounts
        |   ↓
        | access_token OAuth
        |   ↓
        | Preference Mercado Pago
        |
        */

        $checkout = $this->paymentGateway
            ->createCheckout(
                clubId: $clubId,
                externalReference: $externalReference,

                title: sprintf(
                    'Reserva de cancha #%d',
                    $reservation->getId()
                ),

                amount: $amount,
                expiresAt: $expiresAt,
                payerEmail: $command->payerEmail,
            );

        /*
        |--------------------------------------------------------------------------
        | 11. Crear Payment local
        |--------------------------------------------------------------------------
        */

        $payment = new Payment(
            id: null,
            reservationId: $reservation->getId(),
            amount: $amount,
            method: PaymentMethod::MERCADO_PAGO,
            status: PaymentStatus::PENDING,
            provider: 'MERCADO_PAGO',
            providerPreferenceId: $checkout->preferenceId,
            providerPaymentId: null,
            externalReference: $externalReference,
            checkoutUrl: $checkout->checkoutUrl,
            createdByUserId: null,
            paidAt: null,
        );

        /*
        |--------------------------------------------------------------------------
        | 12. Persistir Payment
        |--------------------------------------------------------------------------
        */

        $payment = $this->paymentRepository
            ->save(
                $payment
            );

        /*
        |--------------------------------------------------------------------------
        | 13. Respuesta
        |--------------------------------------------------------------------------
        */

        return new PaymentCheckoutDto(
            paymentId: $payment->getId(),
            amount: $payment->getAmount(),
            percentage: $this->paymentPolicy->percentage(),
            checkoutUrl: $payment->getCheckoutUrl(),
            expiresAt: $expiresAt->format(
                'Y-m-d H:i:s'
            ),
        );
    }
}
