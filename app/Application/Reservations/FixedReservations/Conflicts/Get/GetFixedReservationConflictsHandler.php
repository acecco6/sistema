<?php

namespace App\Application\Reservations\FixedReservations\Conflicts\Get;

use App\Application\Reservations\FixedReservations\DTOs\FixedReservationConflictDto;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationConflictRepository;

final class GetFixedReservationConflictsHandler
{
    public function __construct(
        private FixedReservationConflictRepository $conflicts,
    ) {}

    public function handle(
        GetFixedReservationConflictsQuery $query
    ): array {
        return array_map(
            fn($conflict) =>
            FixedReservationConflictDto::fromDomain(
                $conflict
            ),

            $this->conflicts->findByClubId(
                clubId: $query->clubId,
                resolved: $query->resolved,
            )
        );
    }
}
