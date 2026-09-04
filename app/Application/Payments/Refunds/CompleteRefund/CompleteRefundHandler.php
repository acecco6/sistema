<?php

namespace App\Application\Payments\Refunds\CompleteRefund;

use App\Application\Payments\DTOs\PaymentRefundDto;
use App\Domain\Payments\Events\RefundCompleted;
use App\Domain\Payments\Exceptions\PaymentRefundNotFoundException;
use App\Domain\Payments\Repositories\PaymentRefundRepository;
use Illuminate\Support\Facades\DB;

final class CompleteRefundHandler
{
    public function __construct(
        private PaymentRefundRepository $refunds,
    ) {}

    public function handle(
        CompleteRefundCommand $command
    ): PaymentRefundDto {
        return DB::transaction(function () use ($command) {

            $refund = $this->refunds->findByIdForUpdate(
                $command->refundId
            );

            if ($refund === null) {
                throw new PaymentRefundNotFoundException();
            }

            $refund->complete(
                method: $command->method,
                completedByUserId: $command->completedByUserId,
                notes: $command->notes,
            );

            $updated = $this->refunds->update($refund);

            RefundCompleted::dispatch(
                $updated->getId()
            );

            return PaymentRefundDto::fromDomain($updated);
        }, 3);
    }
}
