<?php

namespace App\Application\Backoffice;

use App\Application\Authorization\AuthorizationService;
use App\Application\Backoffice\Contracts\BackofficeQueryRepository;

final readonly class SearchUsersHandler
{
    public function __construct(
        private BackofficeQueryRepository $queries,
        private AuthorizationService $authorization,
    ) {}

    public function handle(int $authenticatedUserId, int $clubId, ?int $branchId, string $search, ?bool $active, int $page, int $perPage): array
    {
        $this->authorization->authorize($authenticatedUserId, $clubId, $branchId, 'user.view');

        return $this->queries->users($search, $active, $page, $perPage);
    }
}
