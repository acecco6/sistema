<?php

namespace App\Application\Auth\Contracts;

interface TokenGenerator
{
    public function generate(int $userId, string $email): string;
}
