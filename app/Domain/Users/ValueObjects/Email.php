<?php

namespace App\Domain\Users\ValueObjects;

use InvalidArgumentException;

final readonly class Email
{
    public function __construct(
        private string $value
    ) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Dirección de correo electrónico inválida.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(Email $email): bool
    {
        return strtolower($this->value) === strtolower($email->value);
    }
}
