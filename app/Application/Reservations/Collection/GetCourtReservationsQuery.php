<?php

namespace App\Application\Reservations\Collection;

final readonly class GetCourtReservationsQuery
{
    public function __construct(
        public int $courtId,
    ) {}
}
