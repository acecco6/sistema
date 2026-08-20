<?php

namespace App\Application\Auth\Contracts;

interface PasswordHasher
{
    public function check(string $plainText, string $hashedValue): bool;
    
    public function hash(string $plainText): string;
}
