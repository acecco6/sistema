<?php

namespace App\Application\Payments\Gateways;

use App\Application\Payments\DTOs\CheckoutResult;
use App\Application\Payments\DTOs\PaymentGatewayResult;
use DateTimeImmutable;

interface PaymentGateway
{
    public function createCheckout(
        string $externalReference,
        string $title,
        string $amount,
        DateTimeImmutable $expiresAt,
        ?string $payerEmail = null,
    ): CheckoutResult;

    public function getPayment(
        string $providerPaymentId
    ): PaymentGatewayResult;
}
