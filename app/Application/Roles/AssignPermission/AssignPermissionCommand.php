<?php

namespace App\Application\Roles\AssignPermission;

final readonly class AssignPermissionCommand
{
    public function __construct(
        public int $roleId,
        public int $permissionId
    ) {}
}
