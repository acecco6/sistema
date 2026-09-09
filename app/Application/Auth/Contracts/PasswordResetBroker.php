<?php

namespace App\Application\Auth\Contracts;

interface PasswordResetBroker
{
    /**
     * Solicita un enlace de recuperación, sin exponer si el email existe.
     */
    public function sendResetLink(string $email): void;

    /**
     * Restablece la contraseña y revoca las sesiones API del usuario.
     */
    public function reset(string $email, string $token, string $hashedPassword): bool;
}
