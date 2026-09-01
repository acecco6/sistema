<?php

namespace Tests\Feature\Payments;

use App\Application\Payments\Services\ReservationPaymentSummaryService;
use App\Domain\Payments\Enums\FinancialStatus;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\PaymentRefund;

final class ReservationPaymentSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserva_sin_pagos_esta_unpaid(): void
    {
        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->createOne();

        $summary = $this->service()->calculate(
            $this->reservationDomain($reservation->id)
        );

        $this->assertSame('40000.00', $summary->totalPrice);
        $this->assertSame('0.00', $summary->approvedAmount);
        $this->assertSame('20000.00', $summary->requiredDeposit);
        $this->assertSame('40000.00', $summary->remainingAmount);
        $this->assertSame(
            FinancialStatus::UNPAID,
            $summary->financialStatus
        );
    }

    public function test_pago_menor_a_la_sena_esta_partially_paid(): void
    {
        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('10000.00')
            ->createOne();

        $summary = $this->service()->calculate(
            $this->reservationDomain($reservation->id)
        );

        $this->assertSame('10000.00', $summary->approvedAmount);
        $this->assertSame('30000.00', $summary->remainingAmount);
        $this->assertSame(
            FinancialStatus::PARTIALLY_PAID,
            $summary->financialStatus
        );
    }

    public function test_sena_exacta_esta_deposit_paid(): void
    {
        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('20000.00')
            ->createOne();

        $summary = $this->service()->calculate(
            $this->reservationDomain($reservation->id)
        );

        $this->assertSame('20000.00', $summary->approvedAmount);
        $this->assertSame('20000.00', $summary->remainingAmount);
        $this->assertSame(
            FinancialStatus::DEPOSIT_PAID,
            $summary->financialStatus
        );
    }

    public function test_pago_total_esta_paid(): void
    {
        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('40000.00')
            ->createOne();

        $summary = $this->service()->calculate(
            $this->reservationDomain($reservation->id)
        );

        $this->assertSame('40000.00', $summary->approvedAmount);
        $this->assertSame('0.00', $summary->remainingAmount);
        $this->assertSame(
            FinancialStatus::PAID,
            $summary->financialStatus
        );
    }

    public function test_pago_superior_al_total_esta_overpaid(): void
    {
        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('45000.00')
            ->createOne();

        $summary = $this->service()->calculate(
            $this->reservationDomain($reservation->id)
        );

        $this->assertSame('45000.00', $summary->approvedAmount);
        $this->assertSame('0.00', $summary->remainingAmount);
        $this->assertSame(
            FinancialStatus::OVERPAID,
            $summary->financialStatus
        );
    }

    public function test_solo_suma_pagos_approved(): void
    {
        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('20000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('10000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->rejected()
            ->withAmount('5000.00')
            ->createOne();

        $summary = $this->service()->calculate(
            $this->reservationDomain($reservation->id)
        );

        $this->assertSame('20000.00', $summary->approvedAmount);
        $this->assertSame('20000.00', $summary->remainingAmount);
        $this->assertSame(
            FinancialStatus::DEPOSIT_PAID,
            $summary->financialStatus
        );
    }

    public function test_refund_pending_no_modifica_el_pago_neto(): void
    {
        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('20000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('10000.00')
            ->createOne();

        $summary = $this->service()->calculate(
            $this->reservationDomain($reservation->id)
        );

        $this->assertSame('20000.00', $summary->approvedAmount);
        $this->assertSame('0.00', $summary->refundedAmount);
        $this->assertSame('20000.00', $summary->netPaidAmount);
        $this->assertSame('20000.00', $summary->remainingAmount);

        $this->assertSame(
            FinancialStatus::DEPOSIT_PAID,
            $summary->financialStatus
        );
    }

    public function test_refund_completed_resta_del_pago_neto(): void
    {
        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('20000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->completed()
            ->withAmount('5000.00')
            ->createOne();

        $summary = $this->service()->calculate(
            $this->reservationDomain($reservation->id)
        );

        $this->assertSame('20000.00', $summary->approvedAmount);
        $this->assertSame('5000.00', $summary->refundedAmount);
        $this->assertSame('15000.00', $summary->netPaidAmount);
        $this->assertSame('25000.00', $summary->remainingAmount);

        $this->assertSame(
            FinancialStatus::PARTIALLY_PAID,
            $summary->financialStatus
        );
    }

    public function test_refund_total_deja_la_reserva_unpaid(): void
    {
        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('40000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->completed()
            ->withAmount('40000.00')
            ->createOne();

        $summary = $this->service()->calculate(
            $this->reservationDomain($reservation->id)
        );

        $this->assertSame('40000.00', $summary->approvedAmount);
        $this->assertSame('40000.00', $summary->refundedAmount);
        $this->assertSame('0.00', $summary->netPaidAmount);
        $this->assertSame('40000.00', $summary->remainingAmount);

        $this->assertSame(
            FinancialStatus::UNPAID,
            $summary->financialStatus
        );
    }

    public function test_solo_refunds_completed_afectan_el_pago_neto(): void
    {
        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('40000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->completed()
            ->withAmount('5000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('10000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->cancelled()
            ->withAmount('5000.00')
            ->createOne();

        $summary = $this->service()->calculate(
            $this->reservationDomain($reservation->id)
        );

        $this->assertSame('40000.00', $summary->approvedAmount);

        // Solamente el COMPLETED de $5.000.
        $this->assertSame('5000.00', $summary->refundedAmount);

        $this->assertSame('35000.00', $summary->netPaidAmount);
        $this->assertSame('5000.00', $summary->remainingAmount);

        $this->assertSame(
            FinancialStatus::DEPOSIT_PAID,
            $summary->financialStatus
        );
    }

    public function test_refund_de_otra_reserva_no_afecta_el_resumen(): void
    {
        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->createOne();

        $otherReservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('20000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($otherReservation)
            ->completed()
            ->withAmount('15000.00')
            ->createOne();

        $summary = $this->service()->calculate(
            $this->reservationDomain($reservation->id)
        );

        $this->assertSame('20000.00', $summary->approvedAmount);
        $this->assertSame('0.00', $summary->refundedAmount);
        $this->assertSame('20000.00', $summary->netPaidAmount);
        $this->assertSame('20000.00', $summary->remainingAmount);

        $this->assertSame(
            FinancialStatus::DEPOSIT_PAID,
            $summary->financialStatus
        );
    }

    public function test_refund_puede_corregir_un_estado_overpaid(): void
    {
        $reservation = Reservation::factory()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('45000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->completed()
            ->withAmount('5000.00')
            ->createOne();

        $summary = $this->service()->calculate(
            $this->reservationDomain($reservation->id)
        );

        $this->assertSame('45000.00', $summary->approvedAmount);
        $this->assertSame('5000.00', $summary->refundedAmount);
        $this->assertSame('40000.00', $summary->netPaidAmount);
        $this->assertSame('0.00', $summary->remainingAmount);

        $this->assertSame(
            FinancialStatus::PAID,
            $summary->financialStatus
        );
    }

    private function service(): ReservationPaymentSummaryService
    {
        return $this->app->make(
            ReservationPaymentSummaryService::class
        );
    }

    private function reservationDomain(int $id)
    {
        return $this->app
            ->make(\App\Domain\Reservations\Repositories\ReservationRepository::class)
            ->findById($id);
    }
}
