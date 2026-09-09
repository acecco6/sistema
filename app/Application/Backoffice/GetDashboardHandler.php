<?php

namespace App\Application\Backoffice;

use App\Application\Backoffice\Contracts\BackofficeQueryRepository;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;

final readonly class GetDashboardHandler
{
    public function __construct(
        private BranchRepository $branches,
        private BackofficeQueryRepository $queries,
    ) {}

    public function handle(int $branchId, string $date): array
    {
        if ($this->branches->findById($branchId) === null) {
            throw new BranchNotFoundException();
        }

        return $this->queries->dashboard($branchId, $date);
    }
}
