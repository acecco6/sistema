<?php

namespace App\Infrastructure\Payments\Webhooks;

use App\Application\Payments\Webhooks\WebhookSignatureValidator;
use Illuminate\Support\Facades\Log;
use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Webhook\WebhookSignatureValidator as MercadoPagoValidator;
use RuntimeException;

final class MercadoPagoWebhookSignatureValidator implements WebhookSignatureValidator
{
    public function validate(
        string $signature,
        string $requestId,
        string $dataId,
    ): bool {

        $secret = config('services.mercadopago.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            Log::error('MERCADO_PAGO_WEBHOOK_SECRET no está configurado.');
            throw new RuntimeException('Ocurrio un error en el sistema de pago');
        }

        try {
            MercadoPagoValidator::validate(
                $signature,
                $requestId,
                $dataId,
                $secret,
            );

            return true;
        } catch (InvalidWebhookSignatureException) {
            return false;
        }
    }
}
