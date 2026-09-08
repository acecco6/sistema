<?php

namespace App\Domain\Reservations\FixedReservations\Repositories;

use App\Domain\Reservations\FixedReservations\Entities\FixedReservationConflict;
use DateTimeImmutable;

interface FixedReservationConflictRepository
{
    public function findById(
        int $id
    ): ?FixedReservationConflict;

    public function findByOccurrence(
        int $fixedReservationSlotId,
        DateTimeImmutable $recurrenceDate,
    ): ?FixedReservationConflict;

    /**
     * @return FixedReservationConflict[]
     */
    public function findByClubId(
        int $clubId,
        ?bool $resolved = null,
    ): array;

    public function save(
        FixedReservationConflict $conflict
    ): FixedReservationConflict;

    public function update(
        FixedReservationConflict $conflict
    ): FixedReservationConflict;
}
