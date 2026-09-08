<?php

namespace App\Application\Payments\MercadoPagoAccounts\Connect;

final readonly class ConnectMercadoPagoDto
{
    public function __construct(
        public string $authorizationUrl,
    ) {}

    public function toArray(): array
    {
        return [
            'authorization_url' => $this->authorizationUrl,
        ];
    }
}
