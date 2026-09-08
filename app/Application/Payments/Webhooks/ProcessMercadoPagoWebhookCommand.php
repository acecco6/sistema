<?php

namespace App\Application\Payments\Webhooks;

final readonly class ProcessMercadoPagoWebhookCommand
{
    public function __construct(
        public string $providerPaymentId,
        public string $mercadoPagoUserId,
    ) {}
}
