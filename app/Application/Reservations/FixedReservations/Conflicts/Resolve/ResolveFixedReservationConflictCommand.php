<?php

namespace App\Application\Reservations\FixedReservations\Conflicts\Resolve;

final readonly class ResolveFixedReservationConflictCommand
{
    public function __construct(
        public int $id,
        public int $userId,
    ) {}
}
