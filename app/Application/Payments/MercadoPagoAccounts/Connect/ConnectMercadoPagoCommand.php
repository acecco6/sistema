<?php

namespace App\Application\Payments\MercadoPagoAccounts\Connect;

final readonly class ConnectMercadoPagoCommand
{
    public function __construct(
        public int $clubId,
        public int $userId,
    ) {}
}
