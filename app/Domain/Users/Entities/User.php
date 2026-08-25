<?php

namespace App\Domain\Users\Entities;

use App\Domain\Users\ValueObjects\Email;

final class User
{
    public function __construct(
        private ?int $id,
        private string $name,
        private Email $email,
        private string $password,
        private bool $active,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function password(): string
    {
        return $this->password;
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

    public function changeEmail(Email $email): void
    {
        $this->email = $email;
    }
}
