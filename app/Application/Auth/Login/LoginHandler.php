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
            throw new \RuntimeException('Credenciales inválidas.');
        }

        if (!$this->hasher->check($command->password, $user->passwordHash())) {
            throw new \RuntimeException('Credenciales inválidas.');
        }
        return $this->tokenGenerator->generate($user->id(), $email->value());
    }
}
