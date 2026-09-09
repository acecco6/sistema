<?php

namespace App\Application\Auth\ResetPassword;

use App\Application\Auth\Contracts\PasswordHasher;
use App\Application\Auth\Contracts\PasswordResetBroker;

final readonly class ResetPasswordHandler
{
    public function __construct(
        private PasswordResetBroker $passwords,
        private PasswordHasher $hasher,
    ) {}

    public function handle(ResetPasswordCommand $command): bool
    {
        return $this->passwords->reset(
            email: $command->email,
            token: $command->token,
            hashedPassword: $this->hasher->hash($command->password),
        );
    }
}
