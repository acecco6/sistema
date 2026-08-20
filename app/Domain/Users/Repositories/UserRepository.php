<?php

namespace App\Domain\Users\Repositories;

use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\Email;

interface UserRepository
{
    public function findByEmail(Email $email): ?User;

    public function findById(int $id): ?User;

    public function save(User $user): void;
}
