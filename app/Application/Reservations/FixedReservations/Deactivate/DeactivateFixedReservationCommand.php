<?php

namespace App\Application\Reservations\FixedReservations\Deactivate;

final readonly class DeactivateFixedReservationCommand
{
    public function __construct(
        public int $id,
    ) {}
}
