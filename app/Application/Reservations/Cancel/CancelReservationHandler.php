<?php

namespace App\Application\Reservations\Cancel;

use App\Application\Reservations\DTOs\ReservationDto;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class CancelReservationHandler
{
    public function __construct(
        private ReservationRepository $reservations,
    ) {}

    public function handle(CancelReservationCommand $command): ReservationDto
    {
        $reservation = $this->reservations->findById($command->id);

        if ($reservation === null) {
            throw new ReservationNotFoundException();
        }

        $reservation->cancel($command->cancelledAt);

        $updated = $this->reservations->update($reservation);

        return ReservationDto::fromDomain($updated);
    }
}
