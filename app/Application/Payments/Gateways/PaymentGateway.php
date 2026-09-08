<?php

namespace App\Application\Payments\Gateways;

use App\Application\Payments\DTOs\CheckoutResult;
use App\Application\Payments\DTOs\PaymentGatewayResult;
use DateTimeImmutable;

interface PaymentGateway
{
    public function createCheckout(
        int $clubId,
        string $externalReference,
        string $title,
        string $amount,
        DateTimeImmutable $expiresAt,
        ?string $payerEmail = null,
    ): CheckoutResult;

    /*
    |--------------------------------------------------------------------------
    | Temporal
    |--------------------------------------------------------------------------
    |
    | Todavía no agregamos clubId acá porque el webhook actual solamente
    | recibe providerPaymentId y consulta Mercado Pago antes de poder resolver
    | qué Club originó el pago.
    |
    | En el siguiente bloque vamos a modificar el flujo del webhook.
    |
    */
    public function getPayment(
        string $providerPaymentId
    ): PaymentGatewayResult;
}
