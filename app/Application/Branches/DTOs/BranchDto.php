<?php

namespace App\Application\Branches\DTOs;

use App\Domain\Branches\Entities\Branch;

final readonly class BranchDto
{
    public function __construct(
        public int $id,
        public int $clubId,
        public string $name,
        public ?string $address,
        public ?string $openingTime,
        public ?string $closingTime,
        public bool $active,
    ) {}

    public static function fromDomain(Branch $branch): self
    {
        return new self(
            id: $branch->getId(),
            clubId: $branch->getClubId(),
            name: $branch->getName(),
            address: $branch->getAddress(),
            openingTime: $branch->getOpeningTime(),
            closingTime: $branch->getClosingTime(),
            active: $branch->isActive(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->clubId,
            'name' => $this->name,
            'address' => $this->address,
            'opening_time' => $this->openingTime,
            'closing_time' => $this->closingTime,
            'active' => $this->active,
        ];
    }
}
