<?php

namespace App\Application\Reservations\FixedReservations\Get;

final readonly class GetFixedReservationsQuery
{
    public function __construct(
        public int $clubId,
    ) {}
}
