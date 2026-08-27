<?php

namespace App\Application\Reservations\Availability;

use DateTimeImmutable;

final readonly class GetTipoCourtAvailabilityQuery
{
    public function __construct(
        public int $branchId,
        public int $tipoCourtId,
        public DateTimeImmutable $date,
        public ?string $startTime = null,
        public ?string $endTime = null,
    ) {}
}
