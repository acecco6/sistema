<?php

namespace App\Application\Reservations\Show;

final readonly class ShowReservationQuery
{
    public function __construct(
        public int $id,
    ) {}
}
