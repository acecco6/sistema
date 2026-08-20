<?php

namespace App\Infrastructure\Auth;

use App\Application\Auth\Contracts\PasswordHasher;
use Illuminate\Support\Facades\Hash;

final class LaravelPasswordHasher implements PasswordHasher
{
    public function check(string $plainText, string $hashedValue): bool
    {
        return Hash::check($plainText, $hashedValue);
    }

    public function hash(string $plainText): string
    {
        return Hash::make($plainText);
    }
}
