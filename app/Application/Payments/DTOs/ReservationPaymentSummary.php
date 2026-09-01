<?php

namespace App\Application\Payments\DTOs;

use App\Domain\Payments\Enums\FinancialStatus;

final readonly class ReservationPaymentSummary
{
    public function __construct(
        public string $totalPrice,
        public string $approvedAmount,
        public string $requiredDeposit,
        public string $remainingAmount,
        public FinancialStatus $financialStatus,
    ) {}

    public function toArray(): array
    {
        return [
            'total_price' => $this->totalPrice,
            'approved_amount' => $this->approvedAmount,
            'required_deposit' => $this->requiredDeposit,
            'remaining_amount' => $this->remainingAmount,
            'financial_status' => $this->financialStatus->value,
        ];
    }
}
