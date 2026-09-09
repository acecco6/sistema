<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Reservations\FixedReservations\Entities\FixedReservation;
use App\Domain\Reservations\FixedReservations\Entities\FixedReservationSlot;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationRepository;
use App\Models\FixedReservation as FixedReservationModel;
use App\Models\FixedReservationSlot as FixedReservationSlotModel;
use DateTimeImmutable;

final class EloquentFixedReservationRepository implements FixedReservationRepository
{
    public function findById(
        int $id
    ): ?FixedReservation {
        $model = FixedReservationModel::query()
            ->find($id);

        return $model !== null
            ? $this->toDomain($model)
            : null;
    }

    public function findActiveById(
        int $id
    ): ?FixedReservation {
        $model = FixedReservationModel::query()
            ->whereKey($id)
            ->where('active', true)
            ->first();

        return $model !== null
            ? $this->toDomain($model)
            : null;
    }

    public function findByClubId(
        int $clubId
    ): array {
        return FixedReservationModel::query()
            ->where('club_id', $clubId)
            ->orderBy('id')
            ->get()
            ->map(
                fn(FixedReservationModel $model) =>
                $this->toDomain($model)
            )
            ->all();
    }

    public function findActiveByClubId(
        int $clubId
    ): array {
        return FixedReservationModel::query()
            ->where('club_id', $clubId)
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->map(
                fn(FixedReservationModel $model) =>
                $this->toDomain($model)
            )
            ->all();
    }

    public function findActiveBetween(
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): array {
        return FixedReservationModel::query()
            ->where('active', true)

            /*
             * La serie ya debe haber comenzado
             * antes del final de la ventana.
             */
            ->whereDate(
                'starts_on',
                '<=',
                $to->format('Y-m-d')
            )

            /*
             * Si tiene ends_on:
             * debe terminar después del comienzo
             * de la ventana.
             *
             * NULL = serie abierta.
             */
            ->where(function ($query) use ($from) {
                $query
                    ->whereNull('ends_on')
                    ->orWhereDate(
                        'ends_on',
                        '>=',
                        $from->format('Y-m-d')
                    );
            })

            ->orderBy('id')
            ->get()
            ->map(
                fn(FixedReservationModel $model) =>
                $this->toDomain($model)
            )
            ->all();
    }

    public function save(
        FixedReservation $fixedReservation
    ): FixedReservation {
        $model = FixedReservationModel::query()
            ->create(
                $this->fixedReservationData(
                    $fixedReservation
                )
            );

        return $this->toDomain($model);
    }

    public function update(
        FixedReservation $fixedReservation
    ): FixedReservation {
        $model = FixedReservationModel::query()
            ->findOrFail(
                $fixedReservation->getId()
            );

        $model->update(
            $this->fixedReservationData(
                $fixedReservation
            )
        );

        return $this->toDomain(
            $model->fresh()
        );
    }

    public function findSlotsByFixedReservationId(
        int $fixedReservationId
    ): array {
        return FixedReservationSlotModel::query()
            ->where(
                'fixed_reservation_id',
                $fixedReservationId
            )
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(
                fn(FixedReservationSlotModel $model) =>
                $this->slotToDomain($model)
            )
            ->all();
    }

    public function findActiveSlotsByFixedReservationId(
        int $fixedReservationId
    ): array {
        return FixedReservationSlotModel::query()
            ->where(
                'fixed_reservation_id',
                $fixedReservationId
            )
            ->where('active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(
                fn(FixedReservationSlotModel $model) =>
                $this->slotToDomain($model)
            )
            ->all();
    }

    public function findSlotById(
        int $id
    ): ?FixedReservationSlot {
        $model = FixedReservationSlotModel::query()
            ->find($id);

        return $model !== null
            ? $this->slotToDomain($model)
            : null;
    }

    public function saveSlot(
        FixedReservationSlot $slot
    ): FixedReservationSlot {
        $model = FixedReservationSlotModel::query()
            ->create(
                $this->slotData($slot)
            );

        return $this->slotToDomain($model);
    }

    public function updateSlot(
        FixedReservationSlot $slot
    ): FixedReservationSlot {
        $model = FixedReservationSlotModel::query()
            ->findOrFail(
                $slot->getId()
            );

        $model->update(
            $this->slotData($slot)
        );

        return $this->slotToDomain(
            $model->fresh()
        );
    }

    private function fixedReservationData(
        FixedReservation $fixedReservation
    ): array {
        return [
            'club_id' =>
            $fixedReservation->getClubId(),

            'club_customer_id' => $fixedReservation->getClubCustomerId(),

            'customer_user_id' =>
            $fixedReservation->getCustomerUserId(),

            'guest_name' =>
            $fixedReservation->getGuestName(),

            'guest_email' =>
            $fixedReservation->getGuestEmail(),

            'guest_phone' =>
            $fixedReservation->getGuestPhone(),

            'created_by_user_id' =>
            $fixedReservation->getCreatedByUserId(),

            'starts_on' =>
            $fixedReservation
                ->getStartsOn()
                ->format('Y-m-d'),

            'ends_on' =>
            $fixedReservation->getEndsOn()
                ?->format('Y-m-d'),

            'active' =>
            $fixedReservation->isActive(),

            'notes' =>
            $fixedReservation->getNotes(),
        ];
    }

    private function slotData(
        FixedReservationSlot $slot
    ): array {
        return [
            'fixed_reservation_id' =>
            $slot->getFixedReservationId(),

            'court_id' =>
            $slot->getCourtId(),

            'day_of_week' =>
            $slot->getDayOfWeek(),

            'start_time' =>
            $slot->getStartTime(),

            'duration_minutes' =>
            $slot->getDurationMinutes(),

            'active' =>
            $slot->isActive(),
        ];
    }

    private function toDomain(
        FixedReservationModel $model
    ): FixedReservation {
        return new FixedReservation(
            id: $model->id,

            clubId: $model->club_id,

            customerUserId: $model->customer_user_id,

            guestName: $model->guest_name,

            guestEmail: $model->guest_email,

            guestPhone: $model->guest_phone,

            createdByUserId: $model->created_by_user_id,

            startsOn: new DateTimeImmutable(
                $model->starts_on->format('Y-m-d')
            ),

            endsOn: $model->ends_on !== null
                ? new DateTimeImmutable(
                    $model->ends_on->format('Y-m-d')
                )
                : null,

            active: (bool) $model->active,

            notes: $model->notes,
            clubCustomerId: $model->club_customer_id !== null ? (int) $model->club_customer_id : null,
        );
    }

    private function slotToDomain(
        FixedReservationSlotModel $model
    ): FixedReservationSlot {
        return new FixedReservationSlot(
            id: $model->id,

            fixedReservationId: $model->fixed_reservation_id,

            courtId: $model->court_id,

            dayOfWeek: $model->day_of_week,

            startTime: substr(
                (string) $model->start_time,
                0,
                5
            ),

            durationMinutes: $model->duration_minutes,

            active: (bool) $model->active,
        );
    }
}
