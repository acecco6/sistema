<?php

namespace App\Application\Reservations\Availability;

use DateTimeImmutable;

final readonly class GetCourtAvailabilityQuery
{
    public function __construct(
        public int $courtId,
        public DateTimeImmutable $date,
    ) {}
}
