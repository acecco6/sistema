<?php

namespace App\Domain\Roles\Repositories;

use App\Domain\Roles\Entities\Role;

interface RoleRepository
{
    public function findById(int $id): ?Role;

    public function hasPermission(int $roleId, int $permissionId): bool;

    public function attachPermission(int $roleId, int $permissionId): void;

    public function hasPermissionByName(int $roleId, string $permission): bool;

    // public function findAll(): array;

    // public function save(Role $role): Role;

    // public function delete(int $id): void;
}
