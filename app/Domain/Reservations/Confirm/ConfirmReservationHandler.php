<?php

namespace App\Application\Reservations\Confirm;

use App\Application\Reservations\DTOs\ReservationDto;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class ConfirmReservationHandler
{
    public function __construct(
        private ReservationRepository $reservations,
    ) {}

    public function handle(
        ConfirmReservationCommand $command
    ): ReservationDto {
        $reservation = $this->reservations->findById(
            $command->id
        );

        if ($reservation === null) {
            throw new ReservationNotFoundException();
        }

        $reservation->confirm();

        $updated = $this->reservations->update(
            $reservation
        );

        return ReservationDto::fromDomain(
            $updated
        );
    }
}
