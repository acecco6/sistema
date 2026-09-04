<?php

namespace App\Application\Reservations\Collection;

final readonly class GetBranchReservationsQuery
{
    public function __construct(
        public int $branchId,
        public string $date,
    ) {}
}
