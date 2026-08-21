<?php

namespace App\Domain\Roles\Entities;

final class Role
{
    public function __construct(
        private ?int $id,
        private string $name,
        private string $description,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function changeName(string $name): void
    {
        $this->name = $name;
    }

    public function changeDescription(string $description): void
    {
        $this->description = $description;
    }
}
