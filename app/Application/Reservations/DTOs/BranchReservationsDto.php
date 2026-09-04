<?php

namespace App\Application\Reservations\DTOs;

final readonly class BranchReservationsDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $address,
        public array $courts,
    ) {}

    public function toArray(): array
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "address" => $this->address,
            "courts" => $this->courts
        ];
    }
}
