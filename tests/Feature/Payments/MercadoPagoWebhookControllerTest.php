<?php

namespace Tests\Feature\Payments;

use App\Application\Payments\DTOs\PaymentGatewayResult;
use App\Application\Payments\Gateways\PaymentGateway;
use App\Application\Payments\Webhooks\WebhookSignatureValidator;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class MercadoPagoWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_con_firma_invalida_devuelve_401_y_no_modifica_el_pago(): void
    {
        $reservation = Reservation::factory()
            ->pending()
            ->withTotalPrice('40000.00')
            ->createOne([
                'expires_at' => now()->addMinutes(15),
            ]);

        $payment = Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('20000.00')
            ->withExternalReference('PAY-WEBHOOK-001')
            ->createOne();

        $validator = Mockery::mock(
            WebhookSignatureValidator::class
        );

        $validator
            ->shouldReceive('validate')
            ->once()
            ->with(
                signature: 'firma-invalida',
                requestId: 'request-test-001',
                dataId: '123456789',
            )
            ->andReturn(false);

        $this->app->instance(
            WebhookSignatureValidator::class,
            $validator
        );

        $response = $this->postJson(
            '/api/webhooks/mercadopago?data.id=123456789&type=payment',
            [
                'type' => 'payment',
                'data' => [
                    'id' => '123456789',
                ],
            ],
            [
                'x-signature' => 'firma-invalida',
                'x-request-id' => 'request-test-001',
            ]
        );

        $response->assertStatus(401);

        $payment->refresh();
        $reservation->refresh();

        $this->assertSame(
            PaymentStatus::PENDING->value,
            $payment->status
        );

        $this->assertNull(
            $payment->provider_payment_id
        );

        $this->assertNull(
            $payment->paid_at
        );
    }

    public function test_webhook_valido_aprueba_el_pago_y_confirma_la_reserva(): void
    {
        $reservation = Reservation::factory()
            ->pending()
            ->withTotalPrice('40000.00')
            ->createOne([
                'expires_at' => now()->addMinutes(15),
            ]);

        $payment = Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('20000.00')
            ->withExternalReference('PAY-WEBHOOK-VALIDO')
            ->createOne();

        /*
     * 1. Simulamos una firma válida de Mercado Pago.
     */
        $validator = Mockery::mock(
            WebhookSignatureValidator::class
        );

        $validator
            ->shouldReceive('validate')
            ->once()
            ->with(
                signature: 'firma-valida',
                requestId: 'request-test-002',
                dataId: '987654321',
            )
            ->andReturn(true);

        $this->app->instance(
            WebhookSignatureValidator::class,
            $validator
        );

        /*
     * 2. Simulamos la consulta real a Mercado Pago.
     */
        $gateway = Mockery::mock(
            PaymentGateway::class
        );

        $gateway
            ->shouldReceive('getPayment')
            ->once()
            ->with('987654321')
            ->andReturn(
                new PaymentGatewayResult(
                    providerPaymentId: '987654321',
                    status: 'approved',
                    externalReference: 'PAY-WEBHOOK-VALIDO',
                    paidAt: now()->toIso8601String(),
                    amount: '20000.00',
                    currency: 'ARS',
                )
            );

        $this->app->instance(
            PaymentGateway::class,
            $gateway
        );

        /*
     * 3. Mercado Pago llama nuestro endpoint.
     */
        $response = $this->postJson(
            '/api/webhooks/mercadopago?data.id=987654321&type=payment',
            [
                'action' => 'payment.updated',
                'api_version' => 'v1',

                'data' => [
                    'id' => '987654321',
                ],

                'type' => 'payment',
            ],
            [
                'x-signature' => 'firma-valida',
                'x-request-id' => 'request-test-002',
            ]
        );

        /*
     * 4. Mercado Pago debe recibir 200.
     */
        $response->assertOk();

        /*
     * 5. Comprobamos persistencia.
     */
        $payment->refresh();
        $reservation->refresh();

        $this->assertSame(
            PaymentStatus::APPROVED->value,
            $payment->status
        );

        $this->assertSame(
            '987654321',
            $payment->provider_payment_id
        );

        $this->assertNotNull(
            $payment->paid_at
        );

        $this->assertSame(
            ReservationStatus::CONFIRMED,
            $reservation->status
        );

        $this->assertNull(
            $reservation->expires_at
        );
    }


    public function test_webhook_sin_headers_de_seguridad_devuelve_401(): void
    {
        $response = $this->postJson(
            '/api/webhooks/mercadopago?data.id=123456789&type=payment',
            [
                'type' => 'payment',
                'data' => [
                    'id' => '123456789',
                ],
            ]
        );

        $response->assertStatus(401);
    }


    public function test_webhook_de_otro_tipo_se_ignora_con_200(): void
    {
        $validator = Mockery::mock(
            WebhookSignatureValidator::class
        );

        $validator
            ->shouldReceive('validate')
            ->once()
            ->with(
                signature: 'firma-valida',
                requestId: 'request-test-003',
                dataId: '123456789',
            )
            ->andReturn(true);

        $this->app->instance(
            WebhookSignatureValidator::class,
            $validator
        );

        $response = $this->postJson(
            '/api/webhooks/mercadopago?data.id=123456789&type=merchant_order',
            [
                'type' => 'merchant_order',
                'data' => [
                    'id' => '123456789',
                ],
            ],
            [
                'x-signature' => 'firma-valida',
                'x-request-id' => 'request-test-003',
            ]
        );

        $response->assertOk();
    }
}
