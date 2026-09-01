<?php

namespace App\Application\Payments\GetReservationPayments;

use App\Application\Payments\DTOs\PaymentDto;
use App\Application\Payments\DTOs\ReservationPaymentsDto;
use App\Application\Payments\Services\ReservationPaymentSummaryService;
use App\Domain\Payments\Repositories\PaymentRepository;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class GetReservationPaymentsHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly ReservationPaymentSummaryService $paymentSummaryService,
    ) {}

    public function __invoke(GetReservationPaymentsQuery $query): ReservationPaymentsDto
    {
        $reservation = $this->reservationRepository->findById($query->reservationId);

        if ($reservation === null) {
            throw new ReservationNotFoundException();
        }

        $payments = $this->paymentRepository->findByReservation($query->reservationId);

        $paymentDtos = array_map(
            static fn($payment): PaymentDto => PaymentDto::fromDomain($payment),
            $payments,
        );

        $paymentSummary = $this->paymentSummaryService->calculate($reservation);

        return new ReservationPaymentsDto(
            payments: $paymentDtos,
            paymentSummary: $paymentSummary,
        );
    }
}
