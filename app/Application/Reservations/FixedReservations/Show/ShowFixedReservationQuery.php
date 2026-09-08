<?php

namespace App\Application\Reservations\FixedReservations\Show;

final readonly class ShowFixedReservationQuery
{
    public function __construct(
        public int $id,
    ) {}
}
