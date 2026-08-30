<?php

namespace App\Application\Payments\DTOs;

final class PaymentGatewayResult
{
    public function __construct(
        public readonly string $providerPaymentId,
        public readonly string $status,
        public readonly ?string $externalReference,
        public readonly ?string $paidAt,
    ) {}
}
