<?php

namespace App\Application\Memberships\Create;

use App\Domain\Roles\Repositories\RoleRepository;
use App\Domain\Roles\Exceptions\RoleNotFoundException;

use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Branches\Exceptions\{BranchInactiveException, BranchNotFoundException};

use App\Domain\Clubs\Repositories\ClubRepository;
use App\Domain\Clubs\Exceptions\{ClubInactiveException, ClubNotFoundException};

use App\Domain\Users\Repositories\UserRepository;
use App\Domain\Users\Exceptions\{UserInactiveException, UserNotFoundException};

use App\Domain\Memberships\Repositories\MembershipRepository;
use App\Domain\Memberships\Entities\Membership;
use App\Domain\Memberships\Exceptions\{BranchDoesNotBelongToClubException, MembershipAlreadyExistsException};

final class CreateMembershipHandler
{
    public function __construct(
        private MembershipRepository $memberships,
        private UserRepository $users,
        private ClubRepository $clubs,
        private RoleRepository $roles,
        private BranchRepository $branches,
    ) {}

    public function handle(
        CreateMembershipCommand $command
    ): Membership {

        $user = $this->users->findById($command->userId);

        if ($user === null) {
            throw new UserNotFoundException();
        }

        if (! $user->isActive()) {
            throw new UserInactiveException();
        }

        $club = $this->clubs->findById($command->clubId);

        if ($club === null) {
            throw new ClubNotFoundException();
        }

        if (! $club->isActive()) {
            throw new ClubInactiveException();
        }

        $role = $this->roles->findById($command->roleId);

        if ($role === null) {
            throw new RoleNotFoundException();
        }

        if ($command->branchId !== null) {
            $branch = $this->branches->findById($command->branchId);

            if ($branch === null) {
                throw new BranchNotFoundException();
            }

            if (! $branch->isActive()) {
                throw new BranchInactiveException();
            }

            if ($branch->getClubId() !== $club->getId()) {
                throw new BranchDoesNotBelongToClubException();
            }
        }

        $existingMembership = $this->memberships->findForUserAndClub(
            userId: $command->userId,
            clubId: $command->clubId,
            branchId: $command->branchId,
        );

        if ($existingMembership !== null) {
            throw new MembershipAlreadyExistsException();
        }

        if (
            $this->memberships->hasConflictingMembership(
                userId: $command->userId,
                clubId: $command->clubId,
                branchId: $command->branchId,
            )
        ) {
            throw new MembershipAlreadyExistsException();
        }

        $membership = new Membership(
            id: null,
            userId: $command->userId,
            clubId: $command->clubId,
            roleId: $command->roleId,
            branchId: $command->branchId,
            active: true,
        );

        return $this->memberships->create($membership);
    }

    // Casos a probar 
    // ✓ membership a nivel club
    // ✓ membership a nivel branch
    // ✗ user inexistente
    // ✗ club inexistente
    // ✗ club inactivo
    // ✗ role inexistente
    // ✗ branch inexistente
    // ✗ branch inactiva
    // ✗ branch de otro club
    // ✗ membership duplicada
}
