<?php

namespace App\Domain\Users\Repositories;

use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\Email;

interface UserRepository
{
    public function findByEmail(Email $email): ?User;

    public function findById(int $id): ?User;

    /** @return User[] */
    public function findByIds(array $ids): array;

    public function save(User $user): void;
}
