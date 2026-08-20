<?php

namespace App\Application\Auth\Register;


final readonly class RegisterCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
