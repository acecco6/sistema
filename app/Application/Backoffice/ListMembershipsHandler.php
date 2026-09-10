<?php

namespace App\Application\Backoffice;

use App\Application\Authorization\AuthorizationService;
use App\Application\Backoffice\Contracts\BackofficeQueryRepository;
use App\Domain\Memberships\Repositories\MembershipRepository;
use App\Domain\Roles\Repositories\RoleRepository;

final readonly class ListMembershipsHandler
{
    public function __construct(
        private BackofficeQueryRepository $queries,
        private AuthorizationService $authorization,
        private MembershipRepository $memberships,
        private RoleRepository $roles,
    ) {}

    public function handle(int $userId, int $clubId, array $filters, int $page, int $perPage): array
    {
        $this->authorization->authorizeInClub($userId, $clubId, 'membership.view');

        $authorizedMemberships = array_filter(
            $this->memberships->findActiveForClub($userId, $clubId),
            fn($membership) => $this->roles->hasPermissionByName($membership->getRoleId(), 'membership.view'),
        );

        $hasGlobalScope = (bool) array_filter(
            $authorizedMemberships,
            fn($membership) => $membership->getBranchId() === null,
        );

        if (! $hasGlobalScope) {
            $filters['accessible_branch_ids'] = array_values(array_unique(array_map(
                fn($membership) => $membership->getBranchId(),
                $authorizedMemberships,
            )));
        }

        return $this->queries->memberships($clubId, $filters, $page, $perPage, false, $userId);
    }
}
