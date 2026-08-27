<?php

namespace App\Application\Reservations\Show;

use App\Application\Reservations\DTOs\ReservationDto;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class ShowReservationHandler
{
    public function __construct(
        private ReservationRepository $reservations,
    ) {}

    public function handle(ShowReservationQuery $query): ReservationDto
    {
        $reservation = $this->reservations->findById($query->id);

        if ($reservation === null) {
            throw new ReservationNotFoundException();
        }

        return ReservationDto::fromDomain(
            $reservation
        );
    }
}
