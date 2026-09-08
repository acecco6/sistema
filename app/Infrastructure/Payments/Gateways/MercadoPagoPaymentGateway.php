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
        int $mercadoPagoAccountId,
        string $externalReference,
        string $title,
        string $amount,
        DateTimeImmutable $expiresAt,
        ?string $payerEmail = null,
    ): CheckoutResult {
        $this->configureForAccount(
            $mercadoPagoAccountId
        );

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

            'external_reference' =>
            $externalReference,

            'expires' => true,

            'expiration_date_to' =>
            $expiresAt->format(
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
            'X-Idempotency-Key: '
                . $externalReference,
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

    public function getPayment(
        int $mercadoPagoAccountId,
        string $providerPaymentId,
    ): PaymentGatewayResult {
        $this->configureForAccount(
            $mercadoPagoAccountId
        );

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

    private function configureForAccount(
        int $mercadoPagoAccountId
    ): void {
        $account = $this->mercadoPagoAccounts
            ->findById(
                $mercadoPagoAccountId
            );

        if ($account === null) {
            throw new RuntimeException(
                'No se encontró la cuenta de Mercado Pago asociada.'
            );
        }

        $accessToken = $account->getAccessToken();

        if ($accessToken === '') {
            throw new RuntimeException(
                'La cuenta de Mercado Pago no posee un Access Token válido.'
            );
        }

        MercadoPagoConfig::setAccessToken(
            $accessToken
        );
    }
}
