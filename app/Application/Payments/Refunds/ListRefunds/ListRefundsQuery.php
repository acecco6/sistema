<?php

namespace App\Application\Payments\Refunds\ListRefunds;

use App\Domain\Payments\Enums\RefundStatus;

final readonly class ListRefundsQuery
{
    public function __construct(
        public int $branchId,
        public ?RefundStatus $status = null,
    ) {}
}
