<?php

namespace App\Application\Reservations\DTOs;

final readonly class CourtAvailabilityDto
{
    /**
     * @param AvailabilitySlotDto[] $slots
     */
    public function __construct(
        public int $courtId,
        public string $date,
        public int $intervalMinutes,
        public array $slots,
    ) {}

    public function toArray(): array
    {
        return [
            'court_id' => $this->courtId,
            'date' => $this->date,
            'interval_minutes' => $this->intervalMinutes,
            'slots' => array_map(
                fn(AvailabilitySlotDto $slot) =>
                $slot->toArray(),
                $this->slots
            ),
        ];
    }
}
