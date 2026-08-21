<?php

namespace App\Application\Memberships\ChangeRole;

final class ChangeRoleMembershipCommand
{
    public function __construct(
        public readonly int $id,
        public readonly int $roleId,
    ) {}
}
