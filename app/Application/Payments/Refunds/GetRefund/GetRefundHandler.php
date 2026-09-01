<?php

namespace App\Application\Payments\Refunds\GetRefund;

use App\Application\Payments\DTOs\PaymentRefundDto;
use App\Domain\Payments\Exceptions\PaymentRefundNotFoundException;
use App\Domain\Payments\Repositories\PaymentRefundRepository;

final class GetRefundHandler
{
    public function __construct(
        private PaymentRefundRepository $refunds,
    ) {}

    public function handle(
        GetRefundQuery $query
    ): PaymentRefundDto {
        $refund = $this->refunds->findById(
            $query->refundId
        );

        if ($refund === null) {
            throw new PaymentRefundNotFoundException();
        }

        return PaymentRefundDto::fromDomain($refund);
    }
}
