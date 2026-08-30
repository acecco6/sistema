<?php

namespace App\Application\Payments\CreateCheckout;

use App\Application\Payments\DTOs\PaymentCheckoutDto;
use App\Application\Payments\Gateways\PaymentGateway;
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
    ) {}

    public function __invoke(
        CreatePaymentCheckoutCommand $command
    ): PaymentCheckoutDto {
        $reservation = $this->reservationRepository
            ->findById($command->reservationId);

        if ($reservation === null) {
            throw new ReservationNotFoundException();
        }

        if (
            $reservation->getStatus()
            !== ReservationStatus::PENDING
        ) {
            throw new RuntimeException(
                'Solo se puede generar un checkout para una reserva pendiente.'
            );
        }

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
                expiresAt: $expiresAt->format('Y-m-d H:i:s'),
            );
        }

        $amount = $this->paymentPolicy
            ->requiredDeposit(
                $reservation->getTotalPrice()
            );

        $externalReference = sprintf(
            'PAY-%s',
            Uuid::uuid4()->toString()
        );

        $checkout = $this->paymentGateway
            ->createCheckout(
                externalReference: $externalReference,
                title: sprintf(
                    'Reserva de cancha #%d',
                    $reservation->getId()
                ),
                amount: $amount,
                expiresAt: $expiresAt,
                payerEmail: $command->payerEmail,
            );

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

        $payment = $this->paymentRepository->save(
            $payment
        );

        return new PaymentCheckoutDto(
            paymentId: $payment->getId(),
            amount: $payment->getAmount(),
            percentage: $this->paymentPolicy->percentage(),
            checkoutUrl: $payment->getCheckoutUrl(),
            expiresAt: $expiresAt->format('Y-m-d H:i:s'),
        );
    }
}
