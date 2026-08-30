<?php

namespace App\Application\Payments\DTOs;

final class PaymentCheckoutDto
{
    public function __construct(
        public readonly int $paymentId,
        public readonly string $amount,
        public readonly int $percentage,
        public readonly string $checkoutUrl,
        public readonly string $expiresAt,
    ) {}

    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'amount' => $this->amount,
            'percentage' => $this->percentage,
            'checkout_url' => $this->checkoutUrl,
            'expires_at' => $this->expiresAt,
        ];
    }
}
