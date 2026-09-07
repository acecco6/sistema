<?php

namespace App\Infrastructure\Payments\MercadoPago;

use App\Domain\Payments\MercadoPagoAccounts\Services\MercadoPagoOAuthClient;
use App\Domain\Payments\MercadoPagoAccounts\ValueObjects\MercadoPagoOAuthCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MercadoPagoOAuthHttpClient implements MercadoPagoOAuthClient
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = (string) config(
            'services.mercadopago.client_id'
        );

        $this->clientSecret = (string) config(
            'services.mercadopago.client_secret'
        );

        $this->redirectUri = (string) config(
            'services.mercadopago.redirect_uri'
        );

        if (
            $this->clientId === ''
            || $this->clientSecret === ''
            || $this->redirectUri === ''
        ) {
            throw new RuntimeException(
                'Configuración OAuth de Mercado Pago incompleta.'
            );
        }
    }

    public function getAuthorizationUrl(
        string $state
    ): string {
        return 'https://auth.mercadopago.com.ar/authorization?'
            . http_build_query([
                'client_id' => $this->clientId,
                'response_type' => 'code',
                'platform_id' => 'mp',
                'state' => $state,
                'redirect_uri' => $this->redirectUri,
            ]);
    }

    public function exchangeAuthorizationCode(
        string $code,
        string $state
    ): MercadoPagoOAuthCredentials {
        $response = Http::asForm()
            ->acceptJson()
            ->post(
                'https://api.mercadopago.com/oauth/token',
                [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri,
                    'state' => $state,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'No se pudo obtener el Access Token de Mercado Pago.'
            );
        }

        return $this->mapCredentials(
            $response->json()
        );
    }

    public function refreshAccessToken(
        string $refreshToken
    ): MercadoPagoOAuthCredentials {
        $response = Http::asForm()
            ->acceptJson()
            ->post(
                'https://api.mercadopago.com/oauth/token',
                [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'No se pudo renovar el Access Token de Mercado Pago.'
            );
        }

        return $this->mapCredentials(
            $response->json()
        );
    }

    private function mapCredentials(
        array $data
    ): MercadoPagoOAuthCredentials {
        if (
            empty($data['access_token'])
            || empty($data['refresh_token'])
            || empty($data['user_id'])
            || empty($data['expires_in'])
        ) {
            throw new RuntimeException(
                'Mercado Pago devolvió credenciales OAuth incompletas.'
            );
        }

        return new MercadoPagoOAuthCredentials(
            accessToken: (string) $data['access_token'],
            refreshToken: (string) $data['refresh_token'],
            mercadoPagoUserId: (string) $data['user_id'],
            publicKey: isset($data['public_key'])
                ? (string) $data['public_key']
                : null,
            expiresIn: (int) $data['expires_in'],
            scope: isset($data['scope'])
                ? (string) $data['scope']
                : null,
        );
    }
}
