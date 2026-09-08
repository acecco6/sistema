<?php

namespace App\Application\Reservations\FixedReservations\DTOs;

use App\Domain\Reservations\FixedReservations\Entities\FixedReservationConflict;

final readonly class FixedReservationConflictDto
{
    public function __construct(
        public int $id,
        public int $fixedReservationId,
        public int $fixedReservationSlotId,
        public int $courtId,
        public string $recurrenceDate,
        public string $startsAt,
        public string $endsAt,
        public string $reason,
        public ?string $message,
        public bool $resolved,
        public ?int $resolvedByUserId,
        public ?string $resolvedAt,
    ) {}

    public static function fromDomain(
        FixedReservationConflict $conflict
    ): self {
        return new self(
            id: $conflict->getId(),

            fixedReservationId: $conflict->getFixedReservationId(),

            fixedReservationSlotId: $conflict->getFixedReservationSlotId(),

            courtId: $conflict->getCourtId(),

            recurrenceDate: $conflict->getRecurrenceDate()
                ->format('Y-m-d'),

            startsAt: $conflict->getStartsAt()
                ->format('Y-m-d H:i:s'),

            endsAt: $conflict->getEndsAt()
                ->format('Y-m-d H:i:s'),

            reason: $conflict->getReason(),

            message: $conflict->getMessage(),

            resolved: $conflict->isResolved(),

            resolvedByUserId: $conflict->getResolvedByUserId(),

            resolvedAt: $conflict->getResolvedAt()
                ?->format('Y-m-d H:i:s'),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,

            'fixed_reservation_id' =>
            $this->fixedReservationId,

            'fixed_reservation_slot_id' =>
            $this->fixedReservationSlotId,

            'court_id' =>
            $this->courtId,

            'recurrence_date' =>
            $this->recurrenceDate,

            'starts_at' =>
            $this->startsAt,

            'ends_at' =>
            $this->endsAt,

            'reason' =>
            $this->reason,

            'message' =>
            $this->message,

            'resolved' =>
            $this->resolved,

            'resolved_by_user_id' =>
            $this->resolvedByUserId,

            'resolved_at' =>
            $this->resolvedAt,
        ];
    }
}
