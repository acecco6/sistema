<?php

namespace App\Application\Reservations\FixedReservations\Services;

use App\Domain\Reservations\FixedReservations\Entities\FixedReservation;
use App\Domain\Reservations\FixedReservations\Entities\FixedReservationConflict;
use App\Domain\Reservations\FixedReservations\Entities\FixedReservationSlot;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationConflictRepository;
use DateTimeImmutable;

final class FixedReservationConflictService
{
    public function __construct(
        private FixedReservationConflictRepository $conflicts,
    ) {}

    public function register(
        FixedReservation $fixedReservation,
        FixedReservationSlot $slot,
        DateTimeImmutable $recurrenceDate,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        string $reason,
        ?string $message = null,
    ): void {
        $existing = $this->conflicts
            ->findByOccurrence(
                fixedReservationSlotId: $slot->getId(),
                recurrenceDate: $recurrenceDate,
            );

        /*
         * Ya existía:
         * lo reabrimos si había sido marcado resuelto.
         */
        if ($existing !== null) {
            $existing->reopen(
                reason: $reason,
                message: $message,
            );

            $this->conflicts->update(
                $existing
            );

            return;
        }

        $conflict = new FixedReservationConflict(
            id: null,

            fixedReservationId: $fixedReservation->getId(),

            fixedReservationSlotId: $slot->getId(),

            courtId: $slot->getCourtId(),

            recurrenceDate: $recurrenceDate,

            startsAt: $startsAt,

            endsAt: $endsAt,

            reason: $reason,

            message: $message,

            resolved: false,
        );

        $this->conflicts->save(
            $conflict
        );
    }

    public function resolveOccurrence(
        int $fixedReservationSlotId,
        DateTimeImmutable $recurrenceDate,
    ): void {
        $conflict = $this->conflicts
            ->findByOccurrence(
                fixedReservationSlotId: $fixedReservationSlotId,

                recurrenceDate: $recurrenceDate,
            );

        if (
            $conflict === null
            || $conflict->isResolved()
        ) {
            return;
        }

        /*
         * null = resuelto automáticamente por sistema.
         */
        $conflict->resolve(
            userId: null
        );

        $this->conflicts->update(
            $conflict
        );
    }
}
