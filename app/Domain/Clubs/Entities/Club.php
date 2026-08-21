<?php

namespace App\Domain\Clubs\Entities;

final class Club
{
    public function __construct(
        private ?int $id,
        private string $name,
        private bool $active
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function changeName(string $name): void
    {
        $this->name = $name;
    }

    public function changeActive(bool $active): void
    {
        $this->active = $active;
    }
}
