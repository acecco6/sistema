<?php

namespace App\Domain\Payments\MercadoPagoAccounts\ValueObjects;

final readonly class MercadoPagoOAuthCredentials
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public string $mercadoPagoUserId,
        public ?string $publicKey,
        public int $expiresIn,
        public ?string $scope = null,
    ) {}
}
