<?php

namespace App\Application\Reservations\Confirm;

final readonly class ConfirmReservationCommand
{
    public function __construct(
        public int $id,
    ) {}
}
