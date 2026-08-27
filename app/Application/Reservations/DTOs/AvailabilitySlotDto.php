<?php

namespace App\Application\Reservations\DTOs;

final readonly class AvailabilitySlotDto
{
    public function __construct(
        public string $startsAt,
        public string $endsAt,
        public bool $available,
    ) {}

    public function toArray(): array
    {
        return [
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'available' => $this->available,
        ];
    }
}
