<?php

namespace App\Application\Memberships\Show;

use App\Application\Authorization\AuthorizationService;
use App\Application\Backoffice\Contracts\BackofficeQueryRepository;
use App\Domain\Memberships\Exceptions\MembershipNotFoundException;

final readonly class ShowMembershipHandler
{
    public function __construct(
        private BackofficeQueryRepository $queries,
        private AuthorizationService $authorization,
    ) {}

    public function handle(ShowMembershipCommand $command): array
    {
        $membership = $this->queries->membership($command->membershipId);

        if ($membership === null) {
            throw new MembershipNotFoundException();
        }

        $this->authorization->authorize(
            $command->authenticatedUserId,
            $membership['club']['id'],
            $membership['branch']['id'] ?? null,
            'membership.view',
        );

        return $membership;
    }
}
