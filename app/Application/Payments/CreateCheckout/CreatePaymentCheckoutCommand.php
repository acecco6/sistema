<?php

namespace App\Application\Payments\CreateCheckout;

final class CreatePaymentCheckoutCommand
{
    public function __construct(
        public readonly int $reservationId,
        public readonly ?string $payerEmail = null,
    ) {}
}
