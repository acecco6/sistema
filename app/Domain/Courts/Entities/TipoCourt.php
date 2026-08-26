<?php

namespace App\Domain\Courts\Entities;

final class TipoCourt
{
    public function __construct(
        private int $id,
        private string $name,
        private string $description,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /** @return array{id: int, name: string, description: string} */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
        ];
    }
}
