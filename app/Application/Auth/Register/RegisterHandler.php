<?php

namespace App\Application\Auth\Register;

use App\Application\Auth\Contracts\PasswordHasher;
use App\Domain\Users\Entities\User;
use App\Domain\Users\Repositories\UserRepository;
use App\Domain\Users\ValueObjects\Email;

final class RegisterHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
    ) {}

    public function handle(RegisterCommand $command): void
    {
        $email = new Email($command->email);
        // 1. Validar que el email no exista
        if ($this->users->findByEmail($email) !== null) {
            throw new \RuntimeException('El correo electrónico ya está registrado.');
        }

        // 2. Hashear password
        $hashedPassword = $this->hasher->hash($command->password);

        $user = new User(
            id: null,
            name: $command->name,
            email: $email,
            password: $hashedPassword,
            active: true
        );

        // 4. Guardar en repositorio
        $this->users->save($user);
    }
}
