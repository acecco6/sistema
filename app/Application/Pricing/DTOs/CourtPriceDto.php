<?php

namespace App\Application\Pricing\DTOs;

use App\Domain\Pricing\Entities\CourtPrice;

final readonly class CourtPriceDto
{
    public function __construct(
        public ?int $id,
        public int $branchId,
        public int $tipoCourtId,
        public string $price,
        public bool $active,
    ) {}

    public static function fromDomain(
        CourtPrice $price
    ): self {
        return new self(
            id: $price->getId(),
            branchId: $price->getBranchId(),
            tipoCourtId: $price->getTipoCourtId(),
            price: $price->getPrice(),
            active: $price->isActive(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branchId,
            'tipo_court_id' => $this->tipoCourtId,
            'price' => $this->price,
            'active' => $this->active,
        ];
    }
}
