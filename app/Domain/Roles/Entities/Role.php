<?php

namespace App\Domain\Roles\Entities;

final class Role
{
    public function __construct(
        private ?int $id,
        private string $name,
        private string $description,
    ) {}

    public function getId(): ?int
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

    public function changeName(string $name): void
    {
        $this->name = $name;
    }

    public function changeDescription(string $description): void
    {
        $this->description = $description;
    }
}
