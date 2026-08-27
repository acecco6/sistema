<?php

namespace App\Application\Reservations\DTOs;

final readonly class CourtAvailabilitySummaryDto
{
    /**
     * @param AvailabilitySlotDto[] $slots
     */
    public function __construct(
        public int $courtId,
        public string $courtName,
        public array $slots,
    ) {}

    public function toArray(): array
    {
        return [
            'court_id' => $this->courtId,
            'court_name' => $this->courtName,
            'slots' => array_map(
                fn(AvailabilitySlotDto $slot) =>
                $slot->toArray(),
                $this->slots
            ),
        ];
    }
}
