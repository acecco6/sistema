<?php

namespace App\Domain\Payments\MercadoPagoAccounts\Services;

use App\Domain\Payments\MercadoPagoAccounts\ValueObjects\MercadoPagoOAuthCredentials;

interface MercadoPagoOAuthClient
{
    public function getAuthorizationUrl(
        string $state
    ): string;

    public function exchangeAuthorizationCode(
        string $code,
        string $state
    ): MercadoPagoOAuthCredentials;

    public function refreshAccessToken(
        string $refreshToken
    ): MercadoPagoOAuthCredentials;
}
