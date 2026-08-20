<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Users\Entities\User as DomainUser;
use App\Domain\Users\Repositories\UserRepository;
use App\Domain\Users\ValueObjects\Email;
use App\Models\User as EloquentUser;

final class EloquentUserRepository implements UserRepository
{
    public function findByEmail(Email $email): ?DomainUser
    {
        $eloquentUser = EloquentUser::where('email', $email->value())->first();

        if (!$eloquentUser) {
            return null;
        }

        return $this->toDomain($eloquentUser);
    }

    public function findById(int $id): ?DomainUser
    {
        $eloquentUser = EloquentUser::find($id);

        if (!$eloquentUser) {
            return null;
        }

        return $this->toDomain($eloquentUser);
    }

    public function save(DomainUser $user): void
    {
        EloquentUser::updateOrCreate(
            ['id' => $user->id()],
            [
                'name' => $user->name(),
                'email' => $user->email()->value(),
                'password' => $user->passwordHash(),
                'active' => $user->isActive(),
            ]
        );
    }

    protected function toDomain(EloquentUser $eloquentUser): DomainUser
    {
        return new DomainUser(
            id: $eloquentUser->id,
            name: $eloquentUser->name,
            email: new Email($eloquentUser->email),
            passwordHash: $eloquentUser->password,
            active: $eloquentUser->active // (o el campo que tengas en BD)
        );
    }
}
