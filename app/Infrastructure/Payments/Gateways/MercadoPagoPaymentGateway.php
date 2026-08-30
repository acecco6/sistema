<?php

namespace App\Infrastructure\Payments\Gateways;

use App\Application\Payments\DTOs\CheckoutResult;
use App\Application\Payments\DTOs\PaymentGatewayResult;
use App\Application\Payments\Gateways\PaymentGateway;
use DateTimeImmutable;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use RuntimeException;

final class MercadoPagoPaymentGateway implements PaymentGateway
{
    public function __construct()
    {
        $accessToken = config('services.mercadopago.access_token');

        if (! $accessToken) {
            throw new RuntimeException(
                'Mercado Pago access token no configurado.'
            );
        }

        MercadoPagoConfig::setAccessToken($accessToken);
    }

    public function createCheckout(
        string $externalReference,
        string $title,
        string $amount,
        DateTimeImmutable $expiresAt,
        ?string $payerEmail = null,
    ): CheckoutResult {
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

            'notification_url' => config(
                'services.mercadopago.webhook_url'
            ),
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
            empty($preference->id) ||
            empty($preference->init_point)
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

    public function getPayment(
        string $providerPaymentId
    ): PaymentGatewayResult {
        $client = new PaymentClient();

        $payment = $client->get(
            (int) $providerPaymentId
        );

        if (empty($payment->id)) {
            throw new RuntimeException(
                'Pago de Mercado Pago no encontrado.'
            );
        }

        return new PaymentGatewayResult(
            providerPaymentId: (string) $payment->id,
            status: (string) $payment->status,
            externalReference: $payment->external_reference
                ? (string) $payment->external_reference
                : null,
            paidAt: $payment->date_approved
                ? (string) $payment->date_approved
                : null,
        );
    }
}
