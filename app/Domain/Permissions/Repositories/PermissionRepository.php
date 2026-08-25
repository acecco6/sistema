<?php

namespace App\Domain\Permissions\Repositories;

use App\Domain\Permissions\Entities\Permission;

interface PermissionRepository
{
    public function findById(int $id): ?Permission;

    public function findByName(string $name): ?Permission;

    public function findByRoleId(int $roleId): array;
}
