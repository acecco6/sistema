<?php

namespace App\Domain\Permissions\Entities;

final class Permission
{
    public function __construct(
        private ?int $id,
        private readonly string $name,
        private readonly ?string $description
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
