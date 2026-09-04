<?php

namespace App\Application\Payments\Refunds\CreateRefund;

final readonly class CreateRefundCommand
{
    public function __construct(
        public int $reservationId,
        public string $amount,
        public ?string $reason,
        public int $createdByUserId,
    ) {}
}
