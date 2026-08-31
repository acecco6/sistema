<?php

namespace App\Application\Payments\Webhooks;

interface WebhookSignatureValidator
{
    public function validate(
        string $signature,
        string $requestId,
        string $dataId,
    ): bool;
}
