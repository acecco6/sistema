<?php

namespace App\Application\Auth\Login;

use App\Application\Auth\Contracts\PasswordHasher;
use App\Application\Auth\Contracts\TokenGenerator;
use App\Domain\Users\Repositories\UserRepository;
use App\Domain\Users\ValueObjects\Email;

final class LoginHandler
{
    public function __construct(
        private UserRepository $users,
        private TokenGenerator $tokenGenerator,
        private PasswordHasher $hasher,
    ) {}

    public function handle(LoginCommand $command): string
    {
        $email = new Email($command->email);

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            throw new \RuntimeException('Credenciales inválidas.', 401);
        }

        if (!$user->isActive()) {
            throw new \RuntimeException('Usuario inactivo.', 403);
        }

        if (!$this->hasher->check($command->password, $user->password())) {
            throw new \RuntimeException('Credenciales inválidas.', 401);
        }
        return $this->tokenGenerator->generate($user->id(), $email->value());
    }
}
