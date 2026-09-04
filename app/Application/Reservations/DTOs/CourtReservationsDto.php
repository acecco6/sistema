<?php

namespace App\Application\Reservations\DTOs;

final readonly class CourtReservationsDto
{
    public function __construct(
        public int $id,
        public string $name,
        public array $reservations,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'reservations' => $this->reservations,
        ];
    }
}
