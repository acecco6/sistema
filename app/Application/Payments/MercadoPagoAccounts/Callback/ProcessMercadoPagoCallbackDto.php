<?php

namespace App\Application\Payments\MercadoPagoAccounts\Callback;

final readonly class ProcessMercadoPagoCallbackDto
{
    public function __construct(
        public int $clubId,
        public string $mercadoPagoUserId,
        public bool $connected,
    ) {}

    public function toArray(): array
    {
        return [
            'club_id' => $this->clubId,
            'mercado_pago_user_id' => $this->mercadoPagoUserId,
            'connected' => $this->connected,
        ];
    }
}
