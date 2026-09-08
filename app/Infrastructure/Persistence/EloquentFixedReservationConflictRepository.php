<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Reservations\FixedReservations\Entities\FixedReservationConflict;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationConflictRepository;
use App\Models\FixedReservationConflict as EloquentFixedReservationConflict;
use DateTimeImmutable;

final class EloquentFixedReservationConflictRepository
implements FixedReservationConflictRepository
{
    public function findById(
        int $id
    ): ?FixedReservationConflict {
        $model = EloquentFixedReservationConflict::query()
            ->find($id);

        return $model !== null
            ? $this->toDomain($model)
            : null;
    }

    public function findByOccurrence(
        int $fixedReservationSlotId,
        DateTimeImmutable $recurrenceDate,
    ): ?FixedReservationConflict {
        $model = EloquentFixedReservationConflict::query()
            ->where(
                'fixed_reservation_slot_id',
                $fixedReservationSlotId
            )
            ->whereDate(
                'recurrence_date',
                $recurrenceDate->format('Y-m-d')
            )
            ->first();

        return $model !== null
            ? $this->toDomain($model)
            : null;
    }

    public function findByClubId(
        int $clubId,
        ?bool $resolved = null,
    ): array {
        $query = EloquentFixedReservationConflict::query()
            ->whereHas(
                'fixedReservation',
                fn($query) => $query->where(
                    'club_id',
                    $clubId
                )
            );

        if ($resolved !== null) {
            $query->where(
                'resolved',
                $resolved
            );
        }

        return $query
            ->orderBy('recurrence_date')
            ->orderBy('starts_at')
            ->get()
            ->map(
                fn(EloquentFixedReservationConflict $model) =>
                $this->toDomain($model)
            )
            ->all();
    }

    public function save(
        FixedReservationConflict $conflict
    ): FixedReservationConflict {
        $model = EloquentFixedReservationConflict::query()
            ->create(
                $this->toPersistence($conflict)
            );

        return $this->toDomain($model);
    }

    public function update(
        FixedReservationConflict $conflict
    ): FixedReservationConflict {
        $model = EloquentFixedReservationConflict::query()
            ->findOrFail(
                $conflict->getId()
            );

        $model->update(
            $this->toPersistence($conflict)
        );

        return $this->toDomain(
            $model->fresh()
        );
    }

    private function toPersistence(
        FixedReservationConflict $conflict
    ): array {
        return [
            'fixed_reservation_id' =>
            $conflict->getFixedReservationId(),

            'fixed_reservation_slot_id' =>
            $conflict->getFixedReservationSlotId(),

            'court_id' =>
            $conflict->getCourtId(),

            'recurrence_date' =>
            $conflict->getRecurrenceDate()
                ->format('Y-m-d'),

            'starts_at' =>
            $conflict->getStartsAt()
                ->format('Y-m-d H:i:s'),

            'ends_at' =>
            $conflict->getEndsAt()
                ->format('Y-m-d H:i:s'),

            'reason' =>
            $conflict->getReason(),

            'message' =>
            $conflict->getMessage(),

            'resolved' =>
            $conflict->isResolved(),

            'resolved_by_user_id' =>
            $conflict->getResolvedByUserId(),

            'resolved_at' =>
            $conflict->getResolvedAt()
                ?->format('Y-m-d H:i:s'),
        ];
    }

    private function toDomain(
        EloquentFixedReservationConflict $model
    ): FixedReservationConflict {
        return new FixedReservationConflict(
            id: $model->id,

            fixedReservationId: $model->fixed_reservation_id,

            fixedReservationSlotId: $model->fixed_reservation_slot_id,

            courtId: $model->court_id,

            recurrenceDate: new DateTimeImmutable(
                $model->recurrence_date
                    ->format('Y-m-d')
            ),

            startsAt: new DateTimeImmutable(
                $model->starts_at
                    ->format('Y-m-d H:i:s')
            ),

            endsAt: new DateTimeImmutable(
                $model->ends_at
                    ->format('Y-m-d H:i:s')
            ),

            reason: $model->reason,

            message: $model->message,

            resolved: (bool) $model->resolved,

            resolvedByUserId: $model->resolved_by_user_id,

            resolvedAt: $model->resolved_at !== null
                ? new DateTimeImmutable(
                    $model->resolved_at
                        ->format('Y-m-d H:i:s')
                )
                : null,
        );
    }
}
