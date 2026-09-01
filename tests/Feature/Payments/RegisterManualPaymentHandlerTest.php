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

    private function handler(): RegisterManualPaymentHandler
    {
        return $this->app->make(
            RegisterManualPaymentHandler::class
        );
    }
}
