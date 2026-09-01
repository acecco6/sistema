<?php

namespace App\Application\Payments\Refunds\GetRefund;

final readonly class GetRefundQuery
{
    public function __construct(
        public int $refundId,
    ) {}
}
