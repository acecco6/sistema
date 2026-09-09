<?php

namespace App\Application\Backoffice;

use App\Application\Authorization\AuthorizationService;
use App\Application\Backoffice\Contracts\BackofficeQueryRepository;
use App\Domain\Memberships\Exceptions\MembershipNotFoundException;

final readonly class ShowMembershipHandler
{
    public function __construct(
        private BackofficeQueryRepository $queries,
        private AuthorizationService $authorization,
    ) {}

    public function handle(int $userId, int $id): array
    {
        $membership = $this->queries->membership($id);

        if ($membership === null) {
            throw new MembershipNotFoundException();
        }

        $this->authorization->authorize(
            $userId,
            $membership['club']['id'],
            $membership['branch']['id'] ?? null,
            'membership.view',
        );

        return $membership;
    }
}
