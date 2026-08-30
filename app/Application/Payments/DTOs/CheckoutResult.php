<?php

namespace App\Application\Payments\DTOs;

final class CheckoutResult
{
    public function __construct(
        public readonly string $preferenceId,
        public readonly string $checkoutUrl,
    ) {}
}
