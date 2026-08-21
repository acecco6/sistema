<?php

namespace App\Domain\Branches\Entities;

use App\Domain\Branches\Exceptions\BranchAlreadyInactiveException;

final class Branch
{
    public function __construct(
        private ?int $id,
        private int $clubId,
        private string $name,
        private ?string $address,
        private ?string $openingTime,
        private ?string $closingTime,
        private bool $active
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClubId(): int
    {
        return $this->clubId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getOpeningTime(): ?string
    {
        return $this->openingTime;
    }

    public function getClosingTime(): ?string
    {
        return $this->closingTime;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function deactivate(): void
    {
        if (!$this->active) {
            throw new BranchAlreadyInactiveException();
        }
        $this->active = false;
    }

    public function updateDetails(
        string $name, 
        ?string $address, 
        ?string $openingTime, 
        ?string $closingTime, 
        bool $active
    ): void {
        $this->name = $name;
        $this->address = $address;
        $this->openingTime = $openingTime;
        $this->closingTime = $closingTime;
        $this->active = $active;
    }
}
