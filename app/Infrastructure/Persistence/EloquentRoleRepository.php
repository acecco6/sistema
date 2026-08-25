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

    public function hasPermission(int $roleId, int $permissionId): bool
    {
        return EloquentRole::query()
            ->whereKey($roleId)
            ->whereHas('permissions', function ($query) use ($permissionId) {
                $query->whereKey($permissionId);
            })
            ->exists();
    }

    public function attachPermission(int $roleId, int $permissionId): void
    {
        $role = EloquentRole::findOrFail($roleId);
        $role->permissions()->syncWithoutDetaching([$permissionId]);
    }

    public function hasPermissionByName(int $roleId, string $permission): bool
    {
        return EloquentRole::query()
            ->whereKey($roleId)
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('name', $permission);
            })
            ->exists();
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
