<?php

namespace App\Application\Payments\Refunds\ListRefunds;

use App\Application\Payments\DTOs\PaymentRefundDto;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Payments\Repositories\PaymentRefundRepository;

final class ListRefundsHandler
{
    public function __construct(
        private PaymentRefundRepository $refunds,
        private BranchRepository $branches,
    ) {}

    public function handle(
        ListRefundsQuery $query
    ): array {
        $branch = $this->branches->findById(
            $query->branchId
        );

        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        return array_map(
            static fn($refund) =>
            PaymentRefundDto::fromDomain($refund),
            $this->refunds->findByBranch(
                branchId: $query->branchId,
                status: $query->status,
            )
        );
    }
}
