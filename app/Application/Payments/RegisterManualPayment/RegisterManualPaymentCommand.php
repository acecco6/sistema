<?php

namespace App\Application\Payments\RegisterManualPayment;

use App\Domain\Payments\Enums\PaymentMethod;

final readonly class RegisterManualPaymentCommand
{
    public function __construct(
        public int $reservationId,
        public string $amount,
        public PaymentMethod $method,
        public int $createdByUserId,
    ) {}
}
