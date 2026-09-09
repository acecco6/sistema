<?php

namespace App\Application\Auth\ForgotPassword;

use App\Application\Auth\Contracts\PasswordResetBroker;

final readonly class ForgotPasswordHandler
{
    public function __construct(private PasswordResetBroker $passwords) {}

    public function handle(ForgotPasswordCommand $command): void
    {
        $this->passwords->sendResetLink($command->email);
    }
}
