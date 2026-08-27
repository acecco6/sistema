<?php

namespace App\Domain\Pricing\Entities;

final class CourtPrice
{
    public function __construct(
        private ?int $id,
        private int $branchId,
        private int $tipoCourtId,
        private string $price,
        private bool $active,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBranchId(): int
    {
        return $this->branchId;
    }

    public function getTipoCourtId(): int
    {
        return $this->tipoCourtId;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function changePrice(string $price): void
    {
        $this->price = $price;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}
