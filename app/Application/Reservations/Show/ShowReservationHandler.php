<?php

namespace App\Application\Reservations\Show;

use App\Application\Payments\Services\ReservationPaymentSummaryService;
use App\Application\Reservations\DTOs\ReservationDetailsDto;
use App\Application\Reservations\DTOs\ReservationDtoFactory;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class ShowReservationHandler
{
    public function __construct(
        private ReservationRepository $reservations,
        private ReservationPaymentSummaryService $paymentSummaryService,
        private ReservationDtoFactory $dtoFactory,
    ) {}

    public function handle(ShowReservationQuery $query): ReservationDetailsDto
    {

        $reservation = $this->reservations->findById($query->id);

        if ($reservation === null) {
            throw new ReservationNotFoundException();
        }

        $paymentSummary = $this->paymentSummaryService->calculate($reservation);

        return ReservationDetailsDto::fromDomain(
            reservation: $reservation,
            paymentSummary: $paymentSummary,
            reservationDto: $this->dtoFactory->create($reservation),
        );
    }
}
