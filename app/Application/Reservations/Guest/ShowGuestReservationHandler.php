<?php

namespace App\Application\Reservations\Guest;

use App\Application\Reservations\DTOs\GuestReservationDto;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class ShowGuestReservationHandler
{
    public function __construct(
        private ReservationRepository $reservations,
    ) {}

    public function handle(ShowGuestReservationQuery $query): GuestReservationDto
    {

        $reservation = $this->reservations->findByPublicToken($query->publicToken);

        if ($reservation === null || $reservation->getCustomerUserId() !== null) {
            throw new ReservationNotFoundException();
        }

        return GuestReservationDto::fromDomain($reservation);
    }
}
