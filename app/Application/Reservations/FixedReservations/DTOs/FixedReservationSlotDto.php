<?php

namespace App\Application\Reservations\FixedReservations\DTOs;

use App\Domain\Reservations\FixedReservations\Entities\FixedReservationSlot;

final readonly class FixedReservationSlotDto
{
    public function __construct(
        public int $id,
        public int $courtId,
        public int $dayOfWeek,
        public string $startTime,
        public int $durationMinutes,
        public bool $active,
    ) {}

    public static function fromDomain(
        FixedReservationSlot $slot
    ): self {
        return new self(
            id: $slot->getId(),
            courtId: $slot->getCourtId(),
            dayOfWeek: $slot->getDayOfWeek(),
            startTime: $slot->getStartTime(),
            durationMinutes: $slot->getDurationMinutes(),
            active: $slot->isActive(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'court_id' => $this->courtId,
            'day_of_week' => $this->dayOfWeek,
            'start_time' => $this->startTime,
            'duration_minutes' => $this->durationMinutes,
            'active' => $this->active,
        ];
    }
}
