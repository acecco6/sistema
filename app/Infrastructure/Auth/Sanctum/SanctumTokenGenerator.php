<?php

namespace App\Infrastructure\Auth\Sanctum;

use App\Application\Auth\Contracts\TokenGenerator;
use App\Models\User;

final class SanctumTokenGenerator implements TokenGenerator
{
    public function generate(int $userId, string $email): string
    {
        $user = User::findOrFail($userId);
        $token = $user->createToken($email);
        return $token->plainTextToken;
    }
}
