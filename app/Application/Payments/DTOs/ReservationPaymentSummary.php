<?php

namespace App\Application\Payments\DTOs;

use App\Domain\Payments\Enums\FinancialStatus;

final readonly class ReservationPaymentSummary
{
    public function __construct(
        public string $totalPrice,
        public string $approvedAmount,
        public string $requiredDeposit,
        public string $refundedAmount,
        public string $netPaidAmount,
        public string $remainingAmount,
        public FinancialStatus $financialStatus,
    ) {}

    public function toArray(): array
    {
        return [
            'total_price' => $this->totalPrice,
            'approved_amount' => $this->approvedAmount,
            'required_deposit' => $this->requiredDeposit,
            'refunded_amount' => $this->refundedAmount,
            'net_paid_amount' => $this->netPaidAmount,
            'remaining_amount' => $this->remainingAmount,
            'financial_status' => $this->financialStatus->value,
        ];
    }
}
