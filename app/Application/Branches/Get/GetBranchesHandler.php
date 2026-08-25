<?php

namespace App\Application\Branches\Get;

use App\Application\Branches\DTOs\BranchDto;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Memberships\Entities\Membership;
use App\Domain\Memberships\Repositories\MembershipRepository;
use App\Shared\Exceptions\AuthorizationDeniedException;

final class GetBranchesHandler
{
    public function __construct(
        private MembershipRepository $memberships,
        private BranchRepository $branches,
    ) {}

    public function handle(GetBranchesQuery $query): array
    {
        $memberships = $this->memberships->findActiveForClub(
            userId: $query->userId,
            clubId: $query->clubId,
        );

        if ($memberships === []) {
            throw new AuthorizationDeniedException();
        }

        $hasGlobalScope = collect($memberships)
            ->contains(
                fn(Membership $membership) =>
                $membership->getBranchId() === null
            );

        if ($hasGlobalScope) {
            $branches = $this->branches->findByClub(
                $query->clubId
            );

            return array_map(
                BranchDto::fromDomain(...),
                $branches
            );
        }

        $branchIds = array_map(
            fn(Membership $membership) =>
            $membership->getBranchId(),
            $memberships
        );

        $branches = $this->branches->findByClubAndBranchIds(
            clubId: $query->clubId,
            branchIds: $branchIds,
        );

        return array_map(
            BranchDto::fromDomain(...),
            $branches
        );
    }
}
