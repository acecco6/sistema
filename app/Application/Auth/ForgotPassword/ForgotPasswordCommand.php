<?php

namespace App\Application\Auth\ForgotPassword;

final readonly class ForgotPasswordCommand
{
    public function __construct(public string $email) {}
}
