<?php

namespace App\Application\Auth\ResetPassword;

final readonly class ResetPasswordCommand
{
    public function __construct(
        public string $email,
        public string $token,
        public string $password,
    ) {}
}
