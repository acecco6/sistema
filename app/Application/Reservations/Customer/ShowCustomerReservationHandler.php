<?php

namespace App\Application\Reservations\Customer;

use App\Application\Reservations\DTOs\ReservationDto;
use App\Application\Reservations\DTOs\ReservationDtoFactory;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class ShowCustomerReservationHandler
{
    public function __construct(
        private ReservationRepository $reservations,
        private ReservationDtoFactory $dtoFactory,
    ) {}

    public function handle(ShowCustomerReservationQuery $query): ReservationDto
    {

        $reservation = $this->reservations->findById($query->reservationId);

        if ($reservation === null || $reservation->getCustomerUserId() !== $query->customerUserId) {
            throw new ReservationNotFoundException();
        }

        return $this->dtoFactory->create($reservation);
    }
}
