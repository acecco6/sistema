<?php

namespace App\Application\Courts\DTOs;

use App\Domain\Courts\Entities\Court;

final readonly class CourtDto
{
    public function __construct(
        public int $id,
        public int $branchId,
        public int $tipoCourtId,
        public string $name,
        public bool $active,
    ) {}

    public static function fromDomain(Court $court): self
    {
        return new self(
            id:          $court->getId(),
            branchId:    $court->getBranchId(),
            tipoCourtId: $court->getTipoCourtId(),
            name:        $court->getName(),
            active:      $court->isActive(),
        );
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'branch_id'      => $this->branchId,
            'tipo_court_id'  => $this->tipoCourtId,
            'name'           => $this->name,
            'active'         => $this->active,
        ];
    }
}
