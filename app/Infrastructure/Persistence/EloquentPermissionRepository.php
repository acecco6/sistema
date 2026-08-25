<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Permissions\Entities\Permission as DomainPermission;
use App\Domain\Permissions\Repositories\PermissionRepository;
use App\Models\Permission as PermissionModel;

final class EloquentPermissionRepository implements PermissionRepository
{
    public function findById(int $id): ?DomainPermission
    {
        $permission = PermissionModel::find($id);

        return $permission
            ? $this->toDomain($permission)
            : null;
    }

    public function findByName(string $name): ?DomainPermission
    {
        $permission = PermissionModel::query()
            ->where('name', $name)
            ->first();

        return $permission
            ? $this->toDomain($permission)
            : null;
    }

    public function findByRoleId(int $roleId): array
    {
        return PermissionModel::query()
            ->whereHas('roles', function ($query) use ($roleId) {
                $query->where('roles.id', $roleId);
            })
            ->get()
            ->map(fn($permission) => $this->toDomain($permission))
            ->all();
    }

    private function toDomain(PermissionModel $permission): DomainPermission
    {
        return new DomainPermission(
            id: $permission->id,
            name: $permission->name,
            description: $permission->description,
        );
    }
}
