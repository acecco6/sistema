<?php

namespace Tests\Feature\Payments;

use App\Application\Reservations\Cancel\CancelReservationCommand;
use App\Application\Reservations\Cancel\CancelReservationHandler;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Exceptions\InvalidReservationStatusTransitionException;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CancelReservationRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancela_reserva_sin_crear_refund_si_no_fue_solicitado(): void
    {
        $reservation = Reservation::factory()
            ->confirmed()
            ->withTotalPrice('100000.00')
            ->createOne();

        Payment::factory()
            ->approved()
            ->forReservation($reservation)
            ->withAmount('50000.00')
            ->createOne();

        $this->handler()->handle(
            new CancelReservationCommand(
                id: $reservation->id,
                createRefund: false,
            )
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CANCELLED->value,
        ]);

        $this->assertDatabaseCount(
            'payment_refunds',
            0
        );
    }

    public function test_cancela_reserva_y_crea_refund_por_el_total_aprobado(): void
    {
        $user = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->confirmed()
            ->withTotalPrice('100000.00')
            ->createOne();

        Payment::factory()
            ->approved()
            ->forReservation($reservation)
            ->withAmount('25000.00')
            ->createOne();

        Payment::factory()
            ->approved()
            ->forReservation($reservation)
            ->withAmount('25000.00')
            ->createOne();

        $this->handler()->handle(
            new CancelReservationCommand(
                id: $reservation->id,
                createRefund: true,
                refundReason: 'Cancelación administrativa',
                cancelledByUserId: $user->id,
            )
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CANCELLED->value,
        ]);

        $this->assertDatabaseHas('payment_refunds', [
            'reservation_id' => $reservation->id,
            'payment_id' => null,
            'amount' => '50000.00',
            'status' => RefundStatus::PENDING->value,
            'reason' => 'Cancelación administrativa',
            'method' => null,
            'created_by_user_id' => $user->id,
            'completed_by_user_id' => null,
            'completed_at' => null,
        ]);
    }

    public function test_no_crea_refund_si_la_reserva_no_tiene_pagos_aprobados(): void
    {
        $user = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne();

        Payment::factory()
            ->pending()
            ->forReservation($reservation)
            ->withAmount('50000.00')
            ->createOne();

        Payment::factory()
            ->rejected()
            ->forReservation($reservation)
            ->withAmount('20000.00')
            ->createOne();

        $this->handler()->handle(
            new CancelReservationCommand(
                id: $reservation->id,
                createRefund: true,
                refundReason: 'Cancelación administrativa',
                cancelledByUserId: $user->id,
            )
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CANCELLED->value,
        ]);

        $this->assertDatabaseCount(
            'payment_refunds',
            0
        );
    }

    public function test_descuenta_refunds_ya_comprometidos(): void
    {
        $user = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne();

        Payment::factory()
            ->approved()
            ->forReservation($reservation)
            ->withAmount('50000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('20000.00')
            ->createOne();

        $this->handler()->handle(
            new CancelReservationCommand(
                id: $reservation->id,
                createRefund: true,
                refundReason: 'Saldo restante a devolver',
                cancelledByUserId: $user->id,
            )
        );

        /*
         * Approved:        50.000
         * Ya comprometido: 20.000
         * Nuevo refund:    30.000
         */
        $this->assertDatabaseHas('payment_refunds', [
            'reservation_id' => $reservation->id,
            'amount' => '30000.00',
            'status' => RefundStatus::PENDING->value,
        ]);

        $this->assertSame(
            '50000.00',
            $this->committedRefunds($reservation)
        );
    }

    public function test_refund_cancelado_no_se_descuenta_del_monto_a_devolver(): void
    {
        $user = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne();

        Payment::factory()
            ->approved()
            ->forReservation($reservation)
            ->withAmount('50000.00')
            ->createOne();

        /*
         * Este refund ya fue cancelado, por lo tanto
         * no representa una obligación de devolución.
         */
        PaymentRefund::factory()
            ->forReservation($reservation)
            ->cancelled()
            ->withAmount('20000.00')
            ->createOne();

        $this->handler()->handle(
            new CancelReservationCommand(
                id: $reservation->id,
                createRefund: true,
                cancelledByUserId: $user->id,
            )
        );

        $pendingRefund = PaymentRefund::query()
            ->where('reservation_id', $reservation->id)
            ->where('status', RefundStatus::PENDING->value)
            ->first();

        $this->assertNotNull($pendingRefund);

        $this->assertSame(
            '50000.00',
            $pendingRefund->amount
        );
    }

    public function test_no_genera_otro_refund_si_todo_el_dinero_ya_esta_comprometido(): void
    {
        $user = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne();

        Payment::factory()
            ->approved()
            ->forReservation($reservation)
            ->withAmount('50000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('30000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->completed()
            ->withAmount('20000.00')
            ->createOne();

        $this->handler()->handle(
            new CancelReservationCommand(
                id: $reservation->id,
                createRefund: true,
                cancelledByUserId: $user->id,
            )
        );

        $this->assertDatabaseCount(
            'payment_refunds',
            2
        );

        $this->assertSame(
            '50000.00',
            $this->committedRefunds($reservation)
        );
    }

    public function test_si_la_cancelacion_falla_no_se_crea_refund(): void
    {
        $user = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->completed()
            ->createOne();

        Payment::factory()
            ->approved()
            ->forReservation($reservation)
            ->withAmount('50000.00')
            ->createOne();

        try {
            $this->handler()->handle(
                new CancelReservationCommand(
                    id: $reservation->id,
                    createRefund: true,
                    cancelledByUserId: $user->id,
                )
            );

            $this->fail(
                'Se esperaba una excepción al cancelar una reserva completada.'
            );
        } catch (InvalidReservationStatusTransitionException) {
            // Esperado.
        }

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::COMPLETED->value,
        ]);

        $this->assertDatabaseCount(
            'payment_refunds',
            0
        );
    }

    private function handler(): CancelReservationHandler
    {
        return $this->app->make(
            CancelReservationHandler::class
        );
    }

    private function committedRefunds(
        Reservation $reservation
    ): string {
        $sum = PaymentRefund::query()
            ->where('reservation_id', $reservation->id)
            ->whereIn('status', [
                RefundStatus::PENDING->value,
                RefundStatus::COMPLETED->value,
            ])
            ->sum('amount');

        return bcadd(
            (string) $sum,
            '0',
            2
        );
    }
}
