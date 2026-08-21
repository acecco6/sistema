<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Roles\Entities\Role as DomainRole;
use App\Domain\Roles\Repositories\RoleRepository;
use App\Models\Role as EloquentRole;

final class EloquentRoleRepository implements RoleRepository
{
    public function findAll(): array
    {
        $EloquentRoles = EloquentRole::all();

        return $EloquentRoles->map(function (EloquentRole $role) {
            return $this->toDomain($role);
        })->toArray();
    }

    public function findById(int $id): ?DomainRole
    {
        $EloquentRole = EloquentRole::find($id);

        if (!$EloquentRole) {
            return null;
        }

        return $this->toDomain($EloquentRole);
    }


    protected function toDomain(EloquentRole $EloquentRole): DomainRole
    {
        return new DomainRole(
            id: $EloquentRole->id,
            name: $EloquentRole->name,
            description: $EloquentRole->description
        );
    }
}
