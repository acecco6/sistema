<?php

namespace App\Application\Authorization;

use App\Domain\Memberships\Repositories\MembershipRepository;
use App\Domain\Roles\Repositories\RoleRepository;
use App\Shared\Exceptions\AuthorizationDeniedException;

final class AuthorizationService
{
    public function __construct(
        private MembershipRepository $memberships,
        private RoleRepository $roles,
    ) {}

    public function can(int $userId, int $clubId, ?int $branchId, string $permission): bool
    {
        $membership = $this->memberships->findActiveForScope(userId: $userId, clubId: $clubId, branchId: $branchId);

        if ($membership === null) {
            return false;
        }

        return $this->roles->hasPermissionByName(roleId: $membership->getRoleId(), permission: $permission);
    }

    public function authorize(int $userId, int $clubId, ?int $branchId, string $permission,): void
    {
        if (! $this->can(userId: $userId, clubId: $clubId, branchId: $branchId, permission: $permission,)) {
            throw new AuthorizationDeniedException();
        }
    }

    public function canInClub(int $userId, int $clubId, string $permission): bool
    {
        $membership = $this->memberships->findActiveForClub(userId: $userId, clubId: $clubId);

        if ($membership === null) {
            return false;
        }

        return $this->roles->hasPermissionByName(roleId: $membership->getRoleId(), permission: $permission);
    }

    public function authorizeInClub(int $userId, int $clubId, string $permission): void
    {
        if (! $this->canInClub(userId: $userId, clubId: $clubId, permission: $permission)) {
            throw new AuthorizationDeniedException();
        }
    }
}
