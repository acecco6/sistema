<?php

namespace Tests\Feature\Payments;

use App\Application\Payments\RegisterManualPayment\RegisterManualPaymentCommand;
use App\Application\Payments\RegisterManualPayment\RegisterManualPaymentHandler;
use App\Domain\Payments\Enums\FinancialStatus;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Exceptions\InvalidManualPaymentMethodException;
use App\Domain\Payments\Exceptions\InvalidPaymentAmountException;
use App\Domain\Payments\Exceptions\PaymentExceedsRemainingAmountException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RegisterManualPaymentHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_pago_manual_en_efectivo(): void
    {
        $user = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->confirmed()
            ->createOne();

        $summary = $this->handler()(
            new RegisterManualPaymentCommand(
                reservationId: $reservation->id,
                amount: '10000.00',
                method: PaymentMethod::CASH,
                createdByUserId: $user->id,
            )
        );

        $this->assertDatabaseHas('payments', [
            'reservation_id' => $reservation->id,
            'amount' => '10000.00',
            'method' => PaymentMethod::CASH->value,
            'status' => PaymentStatus::APPROVED->value,
            'created_by_user_id' => $user->id,
        ]);

        $this->assertSame(
            '10000.00',
            $summary->approvedAmount
        );

        $this->assertSame(
            '30000.00',
            $summary->remainingAmount
        );

        $this->assertSame(
            FinancialStatus::PARTIALLY_PAID,
            $summary->financialStatus
        );
    }

    public function test_registra_pago_manual_por_transferencia(): void
    {
        $user = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->confirmed()
            ->createOne();

        $summary = $this->handler()(
            new RegisterManualPaymentCommand(
                reservationId: $reservation->id,
                amount: '20000.00',
                method: PaymentMethod::TRANSFER,
                createdByUserId: $user->id,
            )
        );

        $this->assertDatabaseHas('payments', [
            'reservation_id' => $reservation->id,
            'amount' => '20000.00',
            'method' => PaymentMethod::TRANSFER->value,
            'status' => PaymentStatus::APPROVED->value,
        ]);

        $this->assertSame(
            '20000.00',
            $summary->approvedAmount
        );

        $this->assertSame(
            '20000.00',
            $summary->remainingAmount
        );
    }

    public function test_pago_manual_completa_el_saldo_de_la_reserva(): void
    {
        $user = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->confirmed()
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('20000.00')
            ->createOne();

        $summary = $this->handler()(
            new RegisterManualPaymentCommand(
                reservationId: $reservation->id,
                amount: '20000.00',
                method: PaymentMethod::CASH,
                createdByUserId: $user->id,
            )
        );

        $this->assertSame(
            '40000.00',
            $summary->approvedAmount
        );

        $this->assertSame(
            '0.00',
            $summary->remainingAmount
        );

        $this->assertSame(
            FinancialStatus::PAID,
            $summary->financialStatus
        );
    }

    public function test_no_permite_pago_manual_con_monto_cero(): void
    {
        $user = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->confirmed()
            ->createOne();

        $this->expectException(
            InvalidPaymentAmountException::class
        );

        $this->handler()(
            new RegisterManualPaymentCommand(
                reservationId: $reservation->id,
                amount: '0.00',
                method: PaymentMethod::CASH,
                createdByUserId: $user->id,
            )
        );
    }

    public function test_no_permite_pago_manual_con_monto_negativo(): void
    {
        $user = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->confirmed()
            ->createOne();

        $this->expectException(
            InvalidPaymentAmountException::class
        );

        $this->handler()(
            new RegisterManualPaymentCommand(
                reservationId: $reservation->id,
                amount: '-1000.00',
                method: PaymentMethod::CASH,
                createdByUserId: $user->id,
            )
        );
    }

    public function test_no_permite_registrar_un_pago_mayor_al_saldo(): void
    {
        $user = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->confirmed()
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('30000.00')
            ->createOne();

        $this->expectException(
            PaymentExceedsRemainingAmountException::class
        );

        $this->handler()(
            new RegisterManualPaymentCommand(
                reservationId: $reservation->id,
                amount: '15000.00',
                method: PaymentMethod::CASH,
                createdByUserId: $user->id,
            )
        );
    }

    public function test_no_permite_registrar_mercado_pago_manualmente(): void
    {
        $user = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->confirmed()
            ->createOne();

        $this->expectException(
            InvalidManualPaymentMethodException::class
        );

        $this->handler()(
            new RegisterManualPaymentCommand(
                reservationId: $reservation->id,
                amount: '20000.00',
                method: PaymentMethod::MERCADO_PAGO,
                createdByUserId: $user->id,
            )
        );
    }

    public function test_permite_registrar_un_nuevo_pago_despues_de_un_refund_completado(): void
    {
        /*
     |--------------------------------------------------------------------------
     | Arrange
     |--------------------------------------------------------------------------
     */

        $reservation = Reservation::factory()
            ->withTotalPrice('30000.00')
            ->createOne();

        /*
        * La reserva fue pagada completamente.
        */
        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('30000.00')
            ->createOne();

        /*
        * Posteriormente se devolvieron $10.000.
        *
        * Al estar COMPLETED, esos $10.000 ya no forman
        * parte del dinero neto que conserva el club.
        */
        PaymentRefund::factory()
            ->forReservation($reservation)
            ->withAmount('10000.00')
            ->completed()
            ->createOne();

        /*
        * El saldo financiero real ahora es:
        *
        * total      = 30.000
        * approved   = 30.000
        * refunded   = 10.000
        * net paid   = 20.000
        * remaining  = 10.000
        */

        $user = User::factory()->createOne();

        $handler = $this->app->make(
            RegisterManualPaymentHandler::class
        );

        /*
     |--------------------------------------------------------------------------
     | Act
     |--------------------------------------------------------------------------
     */

        $summary = $handler(
            new RegisterManualPaymentCommand(
                reservationId: $reservation->id,
                amount: '10000.00',
                method: PaymentMethod::CASH,
                createdByUserId: $user->id,
            )
        );

        /*
     |--------------------------------------------------------------------------
     | Assert
     |--------------------------------------------------------------------------
     */

        /*
        * Históricamente entraron $40.000.
        */
        $this->assertSame(
            '40000.00',
            $summary->approvedAmount
        );

        /*
        * Pero $10.000 fueron devueltos.
        */
        $this->assertSame(
            '10000.00',
            $summary->refundedAmount
        );

        /*
        * Por lo tanto el dinero neto de la reserva
        * vuelve a ser exactamente $30.000.
        */
        $this->assertSame(
            '30000.00',
            $summary->netPaidAmount
        );

        $this->assertSame(
            '0.00',
            $summary->remainingAmount
        );

        $this->assertSame(
            FinancialStatus::PAID,
            $summary->financialStatus
        );

        /*
        * También verificamos que efectivamente se haya
        * creado el segundo pago.
        */
        $this->assertDatabaseHas('payments', [
            'reservation_id' => $reservation->id,
            'amount' => '10000.00',
            'method' => PaymentMethod::CASH->value,
            'status' => PaymentStatus::APPROVED->value,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_no_permite_nuevo_pago_si_el_refund_sigue_pending(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange
        |--------------------------------------------------------------------------
        */

        $reservation = Reservation::factory()
            ->withTotalPrice('30000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('30000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->withAmount('10000.00')
            ->pending()
            ->createOne();

        $user = User::factory()->createOne();

        $handler = $this->app->make(
            RegisterManualPaymentHandler::class
        );

        /*
        * Aunque existe un refund de $10.000,
        * todavía está PENDING.
        *
        * Por lo tanto:
        *
        * approved     = 30.000
        * refunded     = 0
        * net paid     = 30.000
        * remaining    = 0
        */

        /*
        |--------------------------------------------------------------------------
        | Assert
        |--------------------------------------------------------------------------
        */

        $this->expectException(
            PaymentExceedsRemainingAmountException::class
        );

        /*
        |--------------------------------------------------------------------------
        | Act
        |--------------------------------------------------------------------------
        */

        $handler(
            new RegisterManualPaymentCommand(
                reservationId: $reservation->id,
                amount: '10000.00',
                method: PaymentMethod::CASH,
                createdByUserId: $user->id,
            )
        );
    }

    private function handler(): RegisterManualPaymentHandler
    {
        return $this->app->make(
            RegisterManualPaymentHandler::class
        );
    }
}
