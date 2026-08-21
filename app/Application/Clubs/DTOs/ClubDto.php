<?php

namespace App\Application\Clubs\DTOs;

use App\Domain\Clubs\Entities\Club;


final readonly class ClubDto
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $active,
    ) {}

    public static function fromDomain(Club $club): self
    {
        return new self(
            id: $club->getId(),
            name: $club->getName(),
            active: $club->isActive(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'active' => $this->active,
        ];
    }
}
