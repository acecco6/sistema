<?php

namespace App\Application\Reservations\FixedReservations\Conflicts\Get;

final readonly class GetFixedReservationConflictsQuery
{
    public function __construct(
        public int $clubId,
        public ?bool $resolved = null,
    ) {}
}
