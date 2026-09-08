<?php

namespace App\Infrastructure\Payments\Gateways;

use App\Application\Payments\DTOs\CheckoutResult;
use App\Application\Payments\DTOs\PaymentGatewayResult;
use App\Application\Payments\Gateways\PaymentGateway;
use App\Domain\Payments\MercadoPagoAccounts\Repositories\MercadoPagoAccountRepository;
use DateTimeImmutable;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPNotFoundException;
use MercadoPago\MercadoPagoConfig;
use RuntimeException;

final class MercadoPagoPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly MercadoPagoAccountRepository $mercadoPagoAccounts,
    ) {}

    public function createCheckout(
        int $clubId,
        string $externalReference,
        string $title,
        string $amount,
        DateTimeImmutable $expiresAt,
        ?string $payerEmail = null,
    ): CheckoutResult {
        /*
        |--------------------------------------------------------------------------
        | Configurar Mercado Pago para el Club
        |--------------------------------------------------------------------------
        |
        | Cada Club tiene su propia cuenta de Mercado Pago vinculada mediante
        | OAuth.
        |
        | Nunca usamos el MERCADO_PAGO_ACCESS_TOKEN global para crear
        | preferencias de reservas.
        |
        */

        $this->configureForClub($clubId);

        $client = new PreferenceClient();

        $request = [
            'items' => [
                [
                    'title' => $title,
                    'quantity' => 1,
                    'currency_id' => 'ARS',
                    'unit_price' => (float) $amount,
                ],
            ],

            'external_reference' => $externalReference,

            'expires' => true,

            'expiration_date_to' => $expiresAt->format(
                DATE_ATOM
            ),

            'back_urls' => [
                'success' => config(
                    'services.mercadopago.success_url'
                ),

                'pending' => config(
                    'services.mercadopago.pending_url'
                ),

                'failure' => config(
                    'services.mercadopago.failure_url'
                ),
            ],
        ];

        if ($payerEmail !== null) {
            $request['payer'] = [
                'email' => $payerEmail,
            ];
        }

        $requestOptions = new RequestOptions();

        $requestOptions->setCustomHeaders([
            'X-Idempotency-Key: ' . $externalReference,
        ]);

        $preference = $client->create(
            $request,
            $requestOptions
        );

        if (
            empty($preference->id)
            || empty($preference->init_point)
        ) {
            throw new RuntimeException(
                'Mercado Pago no devolvió una preference válida.'
            );
        }

        return new CheckoutResult(
            preferenceId: (string) $preference->id,
            checkoutUrl: (string) $preference->init_point,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TEMPORAL - Webhook
    |--------------------------------------------------------------------------
    |
    | El webhook actual todavía no sabe a qué Club pertenece el
    | providerPaymentId antes de consultar Mercado Pago.
    |
    | Por eso mantenemos momentáneamente el Access Token global solamente
    | para este método.
    |
    | Este método será migrado en el siguiente paso.
    |
    */

    public function getPayment(
        string $providerPaymentId
    ): PaymentGatewayResult {
        $this->configureGlobalAccessToken();

        $client = new PaymentClient();

        try {
            $payment = $client->get(
                (int) $providerPaymentId
            );
        } catch (MPNotFoundException) {
            throw new RuntimeException(
                'El pago informado por Mercado Pago no existe.'
            );
        }

        if ($payment->id === null) {
            throw new RuntimeException(
                'Mercado Pago no devolvió el ID del pago.'
            );
        }

        if ($payment->transaction_amount === null) {
            throw new RuntimeException(
                'Mercado Pago no devolvió el monto del pago.'
            );
        }

        if ($payment->currency_id === null) {
            throw new RuntimeException(
                'Mercado Pago no devolvió la moneda del pago.'
            );
        }

        return new PaymentGatewayResult(
            providerPaymentId: (string) $payment->id,
            status: (string) $payment->status,

            externalReference: $payment->external_reference !== null
                ? (string) $payment->external_reference
                : null,

            paidAt: $payment->date_approved !== null
                ? (string) $payment->date_approved
                : null,

            amount: number_format(
                (float) $payment->transaction_amount,
                2,
                '.',
                ''
            ),

            currency: (string) $payment->currency_id,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Configuración por Club
    |--------------------------------------------------------------------------
    */

    private function configureForClub(int $clubId): void
    {
        $account = $this->mercadoPagoAccounts
            ->findActiveByClubId($clubId);

        if ($account === null) {
            throw new RuntimeException(
                'El club no tiene una cuenta de Mercado Pago conectada.'
            );
        }

        $accessToken = $account->getAccessToken();

        if ($accessToken === '') {
            throw new RuntimeException(
                'La cuenta de Mercado Pago del club no posee un Access Token válido.'
            );
        }

        MercadoPagoConfig::setAccessToken(
            $accessToken
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Configuración global temporal
    |--------------------------------------------------------------------------
    |
    | SOLO utilizada por getPayment() hasta migrar el webhook.
    |
    */

    private function configureGlobalAccessToken(): void
    {
        $accessToken = config(
            'services.mercadopago.access_token'
        );

        if (! $accessToken) {
            throw new RuntimeException(
                'Mercado Pago access token no configurado.'
            );
        }

        MercadoPagoConfig::setAccessToken(
            $accessToken
        );
    }
}
