<?php

namespace App\Application\Memberships\ChangeBranche;

use App\Application\Memberships\ChangeBranche\ChangeMembershipBranchCommand;
use App\Domain\Branches\Exceptions\BranchInactiveException;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Memberships\Entities\Membership;
use App\Domain\Memberships\Exceptions\BranchDoesNotBelongToClubException;
use App\Domain\Memberships\Exceptions\MembershipNotFoundException;
use App\Domain\Memberships\Exceptions\MembershipScopeConflictException;
use App\Domain\Memberships\Repositories\MembershipRepository;


final class ChangeMembershipBranchHandler
{
    public function __construct(
        private MembershipRepository $memberships,
        private BranchRepository $branches,
    ) {}

    public function handle(ChangeMembershipBranchCommand $command): Membership
    {
        $membership = $this->memberships->findById($command->membershipId);

        if ($membership === null) {
            throw new MembershipNotFoundException();
        }

        if ($command->branchId !== null) {
            $branch = $this->branches->findById(
                $command->branchId
            );

            if ($branch === null) {
                throw new BranchNotFoundException();
            }

            if ($branch->getClubId() !== $membership->getClubId()) {
                throw new BranchDoesNotBelongToClubException();
            }

            if (! $branch->isActive()) {
                throw new BranchInactiveException();
            }
        }

        if (
            $this->memberships->hasConflictingMembership(
                userId: $membership->getUserId(),
                clubId: $membership->getClubId(),
                branchId: $command->branchId,
                excludeMembershipId: $membership->getId(),
            )
        ) {
            throw new MembershipScopeConflictException();
        }
        $membership->changeBranch($command->branchId);
        $this->memberships->update($membership);

        return $membership;
    }
}
