<?php

namespace App\Application\Payments\Refunds\CompleteRefund;

use App\Domain\Payments\Enums\PaymentMethod;

final readonly class CompleteRefundCommand
{
    public function __construct(
        public int $refundId,
        public PaymentMethod $method,
        public int $completedByUserId,
        public ?string $notes = null,
    ) {}
}
