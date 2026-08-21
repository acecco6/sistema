<?php

namespace App\Application\Memberships\ChangeRole;

use App\Domain\Memberships\Exceptions\MembershipNotFoundException;
use App\Domain\Memberships\Repositories\MembershipRepository;
use App\Domain\Roles\Exceptions\RoleNotFoundException;
use App\Domain\Roles\Repositories\RoleRepository;

final class ChangeRoleMembershipHandler
{
    public function __construct(
        private MembershipRepository $membershipRepository,
        private RoleRepository $roleRepository,
    ) {}

    public function handle(ChangeRoleMembershipCommand $command): void
    {
        $membership = $this->membershipRepository->findById($command->id);
        $role = $this->roleRepository->findById($command->roleId);

        if ($membership === null) {
            throw new MembershipNotFoundException();
        }

        if ($role === null) {
            throw new RoleNotFoundException();
        }

        // $role->validatePermissions();
        $membership->changeRole($role->getId());
        $this->membershipRepository->update($membership);
    }
}
