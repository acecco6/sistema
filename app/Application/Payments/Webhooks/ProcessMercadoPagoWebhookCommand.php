<?php

namespace App\Application\Payments\Webhooks;

final class ProcessMercadoPagoWebhookCommand
{
    public function __construct(
        public readonly string $providerPaymentId,
    ) {}
}
