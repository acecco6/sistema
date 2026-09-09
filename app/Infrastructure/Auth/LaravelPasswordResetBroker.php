<?php

namespace App\Infrastructure\Auth;

use App\Application\Auth\Contracts\PasswordResetBroker;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class LaravelPasswordResetBroker implements PasswordResetBroker
{
    public function sendResetLink(string $email): void
    {
        // La respuesta se intencionalmente se ignora para no enumerar cuentas.
        Password::sendResetLink(['email' => $email]);
    }

    public function reset(string $email, string $token, string $hashedPassword): bool
    {
        $status = Password::reset(
            ['email' => $email, 'token' => $token, 'password' => $hashedPassword],
            function (User $user) use ($hashedPassword): void {
                $user->forceFill([
                    'password' => $hashedPassword,
                    'remember_token' => Str::random(60),
                ])->save();

                // Sanctum es infraestructura: invalidamos todas las sesiones API tras el cambio sensible.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        return $status === Password::PASSWORD_RESET;
    }
}
