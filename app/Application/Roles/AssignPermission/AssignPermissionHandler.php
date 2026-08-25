<?php

namespace App\Application\Roles\AssignPermission;

use App\Domain\Permissions\Repositories\PermissionRepository;
use App\Domain\Roles\Exceptions\RoleNotFoundException;
use App\Domain\Roles\Exceptions\PermissionAlreadyAssignedException;
use App\Domain\Roles\Repositories\RoleRepository;
use App\Domain\Permissions\Exceptions\PermissionNotFoundException;

final class AssignPermissionHandler
{
    public function __construct(
        private RoleRepository $roles,
        private PermissionRepository $permissions,
    ) {}

    public function handle(AssignPermissionCommand $command): void
    {
        $role = $this->roles->findById($command->roleId);

        if ($role === null) {
            throw new RoleNotFoundException();
        }

        $permission = $this->permissions->findById(
            $command->permissionId
        );

        if ($permission === null) {
            throw new PermissionNotFoundException();
        }

        if (
            $this->roles->hasPermission(
                roleId: $command->roleId,
                permissionId: $command->permissionId,
            )
        ) {
            throw new PermissionAlreadyAssignedException();
        }

        $this->roles->attachPermission(
            roleId: $command->roleId,
            permissionId: $command->permissionId,
        );
    }
}
