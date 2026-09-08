<?php

namespace App\Application\Payments\MercadoPagoAccounts\Callback;

final readonly class ProcessMercadoPagoCallbackCommand
{
    public function __construct(
        public string $code,
        public string $state,
    ) {}
}
