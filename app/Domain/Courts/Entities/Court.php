<?php

namespace App\Domain\Courts\Entities;

final class Court
{
    public function __construct(
        private int $id,
        private int $branchId,
        private int $tipoCourtId,
        private string $name,
        private bool $active = true,
    ) {}

    public function getId(): int
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

    public function getName(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setTipoCourtId(int $tipoCourtId): self
    {
        $this->tipoCourtId = $tipoCourtId;

        return $this;
    }

    public function activate(): self
    {
        $this->active = true;

        return $this;
    }

    public function deactivate(): self
    {
        $this->active = false;

        return $this;
    }

    /** @return array{id: int, branch_id: int, tipo_court_id: int, name: string, active: bool} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branchId,
            'tipo_court_id' => $this->tipoCourtId,
            'name' => $this->name,
            'active' => $this->active,
        ];
    }
}
