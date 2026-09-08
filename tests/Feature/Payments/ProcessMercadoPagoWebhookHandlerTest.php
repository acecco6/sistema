<?php

namespace Tests\Feature\Payments;

use App\Application\Payments\DTOs\PaymentGatewayResult;
use App\Application\Payments\Gateways\PaymentGateway;
use App\Application\Payments\Webhooks\ProcessMercadoPagoWebhookCommand;
use App\Application\Payments\Webhooks\ProcessMercadoPagoWebhookHandler;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\MercadoPagoAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use App\Domain\Reservations\Events\ReservationConfirmed;
use Illuminate\Support\Facades\Event;

final class ProcessMercadoPagoWebhookHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_pago_aprobado_valido_confirma_la_reserva(): void
    {
        /*
         * Reserva de $40.000.
         *
         * La seña requerida es 50%:
         * $20.000.
         */

        Event::fake([
            ReservationConfirmed::class,
        ]);

        $reservation = Reservation::factory()
            ->pending()
            ->withTotalPrice('40000.00')
            ->createOne([
                'expires_at' => now()->addMinutes(15),
            ]);

        $mercadoPagoAccount = $this->createMercadoPagoAccountForReservation(
            $reservation
        );

        $payment = Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('20000.00')
            ->withExternalReference('PAY-TEST-001')
            ->createOne([
                'mercado_pago_account_id' => $mercadoPagoAccount->id,
            ]);

        /*
         * Simulamos la respuesta REAL que obtendríamos
         * consultando Mercado Pago.
         */
        $gateway = Mockery::mock(PaymentGateway::class);

        $gateway
            ->shouldReceive('getPayment')
            ->once()
            ->with(
                $mercadoPagoAccount->id,
                '123456789'
            )
            ->andReturn(
                new PaymentGatewayResult(
                    providerPaymentId: '123456789',
                    status: 'approved',
                    externalReference: 'PAY-TEST-001',
                    paidAt: now()->toIso8601String(),
                    amount: '20000.00',
                    currency: 'ARS',
                )
            );

        /*
         * Reemplazamos solamente el gateway.
         *
         * Los repositorios y la DB siguen siendo
         * los reales de la aplicación.
         */
        $this->app->instance(
            PaymentGateway::class,
            $gateway
        );

        $handler = $this->app->make(
            ProcessMercadoPagoWebhookHandler::class
        );

        $handler->handle(
            new ProcessMercadoPagoWebhookCommand(
                providerPaymentId: '123456789',
                mercadoPagoUserId: $mercadoPagoAccount->mercado_pago_user_id,
            )
        );

        /*
         * Payment aprobado.
         */
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::APPROVED->value,
            'provider_payment_id' => '123456789',
        ]);

        /*
         * Reserva confirmada.
         */
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CONFIRMED->value,
            'expires_at' => null,
        ]);

        /*
         * Verificamos paid_at aparte porque no necesitamos
         * conocer exactamente el timestamp almacenado.
         */
        $payment->refresh();

        $this->assertNotNull(
            $payment->paid_at
        );

        Event::assertDispatched(
            ReservationConfirmed::class,
            function (ReservationConfirmed $event) use ($reservation) {
                return $event->reservationId === $reservation->id;
            }
        );

        Event::assertDispatchedTimes(ReservationConfirmed::class, 1);
    }


    public function test_procesar_dos_veces_el_mismo_pago_es_idempotente(): void
    {

        Event::fake([
            ReservationConfirmed::class,
        ]);

        $reservation = Reservation::factory()
            ->pending()
            ->withTotalPrice('40000.00')
            ->createOne([
                'expires_at' => now()->addMinutes(15),
            ]);

        $mercadoPagoAccount = $this->createMercadoPagoAccountForReservation(
            $reservation
        );

        $payment = Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('20000.00')
            ->withExternalReference('PAY-TEST-IDEMPOTENCIA')
            ->createOne([
                'mercado_pago_account_id' => $mercadoPagoAccount->id,
            ]);

        $gateway = Mockery::mock(PaymentGateway::class);

        $gateway
            ->shouldReceive('getPayment')
            ->twice()
            ->with(
                $mercadoPagoAccount->id,
                '987654321'
            )
            ->andReturn(
                new PaymentGatewayResult(
                    providerPaymentId: '987654321',
                    status: 'approved',
                    externalReference: 'PAY-TEST-IDEMPOTENCIA',
                    paidAt: now()->toIso8601String(),
                    amount: '20000.00',
                    currency: 'ARS',
                )
            );

        $this->app->instance(
            PaymentGateway::class,
            $gateway
        );

        $handler = $this->app->make(
            ProcessMercadoPagoWebhookHandler::class
        );

        $command = new ProcessMercadoPagoWebhookCommand(
                providerPaymentId: '987654321',
                mercadoPagoUserId: $mercadoPagoAccount->mercado_pago_user_id,
            );

        // Mercado Pago manda el mismo webhook dos veces.
        $handler->handle($command);
        $handler->handle($command);

        Event::assertDispatched(
            ReservationConfirmed::class,
            function (ReservationConfirmed $event) use ($reservation) {
                return $event->reservationId === $reservation->id;
            }
        );

        Event::assertDispatchedTimes(ReservationConfirmed::class, 1);

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

        $this->assertSame(
            ReservationStatus::CONFIRMED,
            $reservation->status
        );

        $this->assertNull(
            $reservation->expires_at
        );

        // Sigue existiendo un solo Payment.
        $this->assertDatabaseCount('payments', 1);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'reservation_id' => $reservation->id,
            'provider_payment_id' => '987654321',
            'status' => PaymentStatus::APPROVED->value,
        ]);
    }


    public function test_pago_aprobado_despues_de_expirar_no_confirma_la_reserva(): void
    {

        Event::fake([
            ReservationConfirmed::class,
        ]);

        $reservation = Reservation::factory()
            ->pending()
            ->withTotalPrice('40000.00')
            ->createOne([
                'expires_at' => now()->subMinute(),
            ]);

        $mercadoPagoAccount = $this->createMercadoPagoAccountForReservation(
            $reservation
        );

        $payment = Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('20000.00')
            ->withExternalReference('PAY-TEST-EXPIRADO')
            ->createOne([
                'mercado_pago_account_id' => $mercadoPagoAccount->id,
            ]);

        $gateway = Mockery::mock(PaymentGateway::class);

        $gateway
            ->shouldReceive('getPayment')
            ->once()
            ->with(
                $mercadoPagoAccount->id,
                '111222333'
            )
            ->andReturn(
                new PaymentGatewayResult(
                    providerPaymentId: '111222333',
                    status: 'approved',
                    externalReference: 'PAY-TEST-EXPIRADO',
                    paidAt: now()->toIso8601String(),
                    amount: '20000.00',
                    currency: 'ARS',
                )
            );

        $this->app->instance(
            PaymentGateway::class,
            $gateway
        );

        $handler = $this->app->make(
            ProcessMercadoPagoWebhookHandler::class
        );

        $handler->handle(
            new ProcessMercadoPagoWebhookCommand(
                providerPaymentId: '111222333',
                mercadoPagoUserId: $mercadoPagoAccount->mercado_pago_user_id,
            )
        );

        $payment->refresh();
        $reservation->refresh();

        /*
        * El dinero realmente fue cobrado, por lo tanto
        * el Payment debe registrarse como APPROVED.
        */
        $this->assertSame(
            PaymentStatus::APPROVED->value,
            $payment->status
        );

        $this->assertSame(
            '111222333',
            $payment->provider_payment_id
        );

        $this->assertNotNull(
            $payment->paid_at
        );

        /*
        * Pero la reserva NO puede confirmarse porque
        * el tiempo de retención ya venció.
        */
        $this->assertSame(
            ReservationStatus::PENDING,
            $reservation->status
        );

        $this->assertNotNull(
            $reservation->expires_at
        );

        Event::assertNotDispatched(ReservationConfirmed::class);
    }

    public function test_pago_con_monto_incorrecto_no_confirma_la_reserva(): void
    {
        $reservation = Reservation::factory()
            ->pending()
            ->withTotalPrice('40000.00')
            ->createOne([
                'expires_at' => now()->addMinutes(15),
            ]);

        $mercadoPagoAccount = $this->createMercadoPagoAccountForReservation(
            $reservation
        );

        $payment = Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('20000.00')
            ->withExternalReference('PAY-TEST-MONTO-INCORRECTO')
            ->createOne([
                'mercado_pago_account_id' => $mercadoPagoAccount->id,
            ]);

        $gateway = Mockery::mock(PaymentGateway::class);

        $gateway
            ->shouldReceive('getPayment')
            ->once()
            ->with(
                $mercadoPagoAccount->id,
                '444555666'
            )
            ->andReturn(
                new PaymentGatewayResult(
                    providerPaymentId: '444555666',
                    status: 'approved',
                    externalReference: 'PAY-TEST-MONTO-INCORRECTO',
                    paidAt: now()->toIso8601String(),
                    amount: '10000.00',
                    currency: 'ARS',
                )
            );

        $this->app->instance(
            PaymentGateway::class,
            $gateway
        );

        $handler = $this->app->make(
            ProcessMercadoPagoWebhookHandler::class
        );

        try {
            $handler->handle(
                new ProcessMercadoPagoWebhookCommand(
                providerPaymentId: '444555666',
                mercadoPagoUserId: $mercadoPagoAccount->mercado_pago_user_id,
            )
            );

            $this->fail(
                'Se esperaba una excepción por monto incorrecto.'
            );
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'El monto recibido no coincide con el monto esperado.',
                $exception->getMessage()
            );
        }

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

        $this->assertSame(
            ReservationStatus::PENDING,
            $reservation->status
        );

        $this->assertNotNull(
            $reservation->expires_at
        );
    }


    public function test_pago_con_moneda_incorrecta_no_confirma_la_reserva(): void
    {
        $reservation = Reservation::factory()
            ->pending()
            ->withTotalPrice('40000.00')
            ->createOne([
                'expires_at' => now()->addMinutes(15),
            ]);

        $mercadoPagoAccount = $this->createMercadoPagoAccountForReservation(
            $reservation
        );

        $payment = Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('20000.00')
            ->withExternalReference('PAY-TEST-MONEDA-INCORRECTA')
            ->createOne([
                'mercado_pago_account_id' => $mercadoPagoAccount->id,
            ]);

        $gateway = Mockery::mock(PaymentGateway::class);

        $gateway
            ->shouldReceive('getPayment')
            ->once()
            ->with(
                $mercadoPagoAccount->id,
                '777888999'
            )
            ->andReturn(
                new PaymentGatewayResult(
                    providerPaymentId: '777888999',
                    status: 'approved',
                    externalReference: 'PAY-TEST-MONEDA-INCORRECTA',
                    paidAt: now()->toIso8601String(),
                    amount: '20000.00',

                    // Incorrecta a propósito.
                    currency: 'USD',
                )
            );

        $this->app->instance(
            PaymentGateway::class,
            $gateway
        );

        $handler = $this->app->make(
            ProcessMercadoPagoWebhookHandler::class
        );

        try {
            $handler->handle(
                new ProcessMercadoPagoWebhookCommand(
                providerPaymentId: '777888999',
                mercadoPagoUserId: $mercadoPagoAccount->mercado_pago_user_id,
            )
            );

            $this->fail(
                'Se esperaba una excepción por moneda incorrecta.'
            );
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'La moneda del pago no es válida.',
                $exception->getMessage()
            );
        }

        $payment->refresh();
        $reservation->refresh();

        // El Payment local no debe modificarse.
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

        // Y mucho menos confirmar la reserva.
        $this->assertSame(
            ReservationStatus::PENDING,
            $reservation->status
        );

        $this->assertNotNull(
            $reservation->expires_at
        );
    }

    public function test_pago_rechazado_marca_el_payment_como_rejected_y_no_confirma_la_reserva(): void
    {
        $reservation = Reservation::factory()
            ->pending()
            ->withTotalPrice('40000.00')
            ->createOne([
                'expires_at' => now()->addMinutes(15),
            ]);

        $mercadoPagoAccount = $this->createMercadoPagoAccountForReservation(
            $reservation
        );

        $payment = Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('20000.00')
            ->withExternalReference('PAY-TEST-REJECTED')
            ->createOne([
                'mercado_pago_account_id' => $mercadoPagoAccount->id,
            ]);

        $gateway = Mockery::mock(PaymentGateway::class);

        $gateway
            ->shouldReceive('getPayment')
            ->once()
            ->with(
                $mercadoPagoAccount->id,
                '555666777'
            )
            ->andReturn(
                new PaymentGatewayResult(
                    providerPaymentId: '555666777',
                    status: 'rejected',
                    externalReference: 'PAY-TEST-REJECTED',
                    paidAt: null,
                    amount: '20000.00',
                    currency: 'ARS',
                )
            );

        $this->app->instance(
            PaymentGateway::class,
            $gateway
        );

        $handler = $this->app->make(
            ProcessMercadoPagoWebhookHandler::class
        );

        $handler->handle(
            new ProcessMercadoPagoWebhookCommand(
                providerPaymentId: '555666777',
                mercadoPagoUserId: $mercadoPagoAccount->mercado_pago_user_id,
            )
        );

        $payment->refresh();
        $reservation->refresh();

        /*
     * Mercado Pago rechazó el pago.
     */
        $this->assertSame(
            PaymentStatus::REJECTED->value,
            $payment->status
        );

        $this->assertSame(
            '555666777',
            $payment->provider_payment_id
        );

        $this->assertNull(
            $payment->paid_at
        );

        /*
     * La reserva sigue esperando un pago válido.
     */
        $this->assertSame(
            ReservationStatus::PENDING,
            $reservation->status
        );

        $this->assertNotNull(
            $reservation->expires_at
        );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::REJECTED->value,
            'provider_payment_id' => '555666777',
        ]);
    }


    public function test_pago_cancelado_marca_el_payment_como_cancelled_y_no_confirma_la_reserva(): void
    {
        $reservation = Reservation::factory()
            ->pending()
            ->withTotalPrice('40000.00')
            ->createOne([
                'expires_at' => now()->addMinutes(15),
            ]);

        $mercadoPagoAccount = $this->createMercadoPagoAccountForReservation(
            $reservation
        );

        $payment = Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('20000.00')
            ->withExternalReference('PAY-TEST-CANCELLED')
            ->createOne([
                'mercado_pago_account_id' => $mercadoPagoAccount->id,
            ]);

        $gateway = Mockery::mock(PaymentGateway::class);

        $gateway
            ->shouldReceive('getPayment')
            ->once()
            ->with(
                $mercadoPagoAccount->id,
                '888999000'
            )
            ->andReturn(
                new PaymentGatewayResult(
                    providerPaymentId: '888999000',
                    status: 'cancelled',
                    externalReference: 'PAY-TEST-CANCELLED',
                    paidAt: null,
                    amount: '20000.00',
                    currency: 'ARS',
                )
            );

        $this->app->instance(
            PaymentGateway::class,
            $gateway
        );

        $handler = $this->app->make(
            ProcessMercadoPagoWebhookHandler::class
        );

        $handler->handle(
            new ProcessMercadoPagoWebhookCommand(
                providerPaymentId: '888999000',
                mercadoPagoUserId: $mercadoPagoAccount->mercado_pago_user_id,
            )
        );

        $payment->refresh();
        $reservation->refresh();

        $this->assertSame(
            PaymentStatus::CANCELLED->value,
            $payment->status
        );

        $this->assertSame(
            '888999000',
            $payment->provider_payment_id
        );

        $this->assertNull(
            $payment->paid_at
        );

        $this->assertSame(
            ReservationStatus::PENDING,
            $reservation->status
        );

        $this->assertNotNull(
            $reservation->expires_at
        );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::CANCELLED->value,
            'provider_payment_id' => '888999000',
        ]);
    }

    public function test_pago_con_external_reference_desconocido_no_modifica_ninguna_reserva(): void
    {
        $reservation = Reservation::factory()
            ->pending()
            ->withTotalPrice('40000.00')
            ->createOne([
                'expires_at' => now()->addMinutes(15),
            ]);

        $mercadoPagoAccount = $this->createMercadoPagoAccountForReservation(
            $reservation
        );

        $payment = Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('20000.00')
            ->withExternalReference('PAY-LOCAL-001')
            ->createOne([
                'mercado_pago_account_id' => $mercadoPagoAccount->id,
            ]);

        $gateway = Mockery::mock(PaymentGateway::class);

        $gateway
            ->shouldReceive('getPayment')
            ->once()
            ->with(
                $mercadoPagoAccount->id,
                '999111222'
            )
            ->andReturn(
                new PaymentGatewayResult(
                    providerPaymentId: '999111222',
                    status: 'approved',
                    externalReference: 'PAY-DESCONOCIDO',
                    paidAt: now()->toIso8601String(),
                    amount: '20000.00',
                    currency: 'ARS',
                )
            );

        $this->app->instance(
            PaymentGateway::class,
            $gateway
        );

        $handler = $this->app->make(
            ProcessMercadoPagoWebhookHandler::class
        );

        try {
            $handler->handle(
                new ProcessMercadoPagoWebhookCommand(
                providerPaymentId: '999111222',
                mercadoPagoUserId: $mercadoPagoAccount->mercado_pago_user_id,
            )
            );

            $this->fail(
                'Se esperaba una excepción porque el external_reference no existe.'
            );
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'No se encontró el pago asociado.',
                $exception->getMessage()
            );
        }

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

        $this->assertSame(
            ReservationStatus::PENDING,
            $reservation->status
        );

        $this->assertNotNull(
            $reservation->expires_at
        );

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_pago_reembolsado_marca_el_payment_como_refunded_y_no_modifica_la_reserva(): void
    {
        $reservation = Reservation::factory()
            ->pending()
            ->withTotalPrice('40000.00')
            ->createOne([
                'expires_at' => now()->addMinutes(15),
            ]);

        $mercadoPagoAccount = $this->createMercadoPagoAccountForReservation(
            $reservation
        );

        $payment = Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('20000.00')
            ->withExternalReference('PAY-TEST-REFUNDED')
            ->createOne([
                'mercado_pago_account_id' => $mercadoPagoAccount->id,
            ]);

        $gateway = Mockery::mock(PaymentGateway::class);

        $gateway
            ->shouldReceive('getPayment')
            ->once()
            ->with(
                $mercadoPagoAccount->id,
                '222333444'
            )
            ->andReturn(
                new PaymentGatewayResult(
                    providerPaymentId: '222333444',
                    status: 'refunded',
                    externalReference: 'PAY-TEST-REFUNDED',
                    paidAt: null,
                    amount: '20000.00',
                    currency: 'ARS',
                )
            );

        $this->app->instance(
            PaymentGateway::class,
            $gateway
        );

        $handler = $this->app->make(
            ProcessMercadoPagoWebhookHandler::class
        );

        $handler->handle(
            new ProcessMercadoPagoWebhookCommand(
                providerPaymentId: '222333444',
                mercadoPagoUserId: $mercadoPagoAccount->mercado_pago_user_id,
            )
        );

        $payment->refresh();
        $reservation->refresh();

        $this->assertSame(
            PaymentStatus::REFUNDED->value,
            $payment->status
        );

        $this->assertSame(
            '222333444',
            $payment->provider_payment_id
        );

        $this->assertNull(
            $payment->paid_at
        );

        /*
     * Por ahora un refund NO cambia automáticamente
     * el estado de la reserva.
     *
     * Esa política la vamos a definir aparte.
     */
        $this->assertSame(
            ReservationStatus::PENDING,
            $reservation->status
        );

        $this->assertNotNull(
            $reservation->expires_at
        );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::REFUNDED->value,
            'provider_payment_id' => '222333444',
        ]);
    }


    public function test_estado_de_pago_no_contemplado_no_confirma_la_reserva(): void
    {
        $reservation = Reservation::factory()
            ->pending()
            ->withTotalPrice('40000.00')
            ->createOne([
                'expires_at' => now()->addMinutes(15),
            ]);

        $mercadoPagoAccount = $this->createMercadoPagoAccountForReservation(
            $reservation
        );

        $payment = Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('20000.00')
            ->withExternalReference('PAY-TEST-IN-PROCESS')
            ->createOne([
                'mercado_pago_account_id' => $mercadoPagoAccount->id,
            ]);

        $gateway = Mockery::mock(PaymentGateway::class);

        $gateway
            ->shouldReceive('getPayment')
            ->once()
            ->with(
                $mercadoPagoAccount->id,
                '333444555'
            )
            ->andReturn(
                new PaymentGatewayResult(
                    providerPaymentId: '333444555',
                    status: 'in_process',
                    externalReference: 'PAY-TEST-IN-PROCESS',
                    paidAt: null,
                    amount: '20000.00',
                    currency: 'ARS',
                )
            );

        $this->app->instance(
            PaymentGateway::class,
            $gateway
        );

        $handler = $this->app->make(
            ProcessMercadoPagoWebhookHandler::class
        );

        $handler->handle(
            new ProcessMercadoPagoWebhookCommand(
                providerPaymentId: '333444555',
                mercadoPagoUserId: $mercadoPagoAccount->mercado_pago_user_id,
            )
        );

        $payment->refresh();
        $reservation->refresh();

        /*
        * Un estado transitorio/desconocido
        * nunca debe aprobar el Payment.
        */
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

        $this->assertSame(
            ReservationStatus::PENDING,
            $reservation->status
        );

        $this->assertNotNull(
            $reservation->expires_at
        );
    }

    private function createMercadoPagoAccountForReservation(
        Reservation $reservation
    ): MercadoPagoAccount {
        $court = \App\Models\Court::query()
            ->findOrFail($reservation->court_id);

        $branch = \App\Models\Branch::query()
            ->findOrFail($court->branch_id);

        return MercadoPagoAccount::create([
            'club_id' => $branch->club_id,
            'mercado_pago_user_id' => 'TEST-SELLER-' . $branch->club_id,
            'access_token' => 'TEST-ACCESS-TOKEN',
            'refresh_token' => 'TEST-REFRESH-TOKEN',
            'expires_at' => now()->addMonths(6),
            'public_key' => 'TEST-PUBLIC-KEY',
            'active' => true,
            'connected_at' => now(),
        ]);
    }

}
