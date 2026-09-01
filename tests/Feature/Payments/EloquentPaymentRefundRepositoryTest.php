<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Payments\Repositories\PaymentRefundRepository;
use App\Models\PaymentRefund;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentPaymentRefundRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_busca_refund_por_id(): void
    {
        $refundModel = PaymentRefund::factory()
            ->withAmount('15000.00')
            ->pending()
            ->createOne();

        $refund = $this->repository()->findById(
            $refundModel->id
        );

        $this->assertNotNull($refund);

        $this->assertSame(
            $refundModel->id,
            $refund->getId()
        );

        $this->assertSame(
            '15000.00',
            $refund->getAmount()
        );

        $this->assertSame(
            RefundStatus::PENDING,
            $refund->getStatus()
        );
    }

    public function test_busca_refunds_de_una_reserva_en_orden_cronologico(): void
    {
        $reservation = Reservation::factory()->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->withAmount('20000.00')
            ->createOne([
                'created_at' => now()->subMinutes(5),
            ]);

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->withAmount('10000.00')
            ->createOne([
                'created_at' => now()->subMinutes(15),
            ]);

        $refunds = $this->repository()
            ->findByReservation($reservation->id);

        $this->assertCount(2, $refunds);

        $this->assertSame(
            '10000.00',
            $refunds[0]->getAmount()
        );

        $this->assertSame(
            '20000.00',
            $refunds[1]->getAmount()
        );
    }

    public function test_find_pending_devuelve_solamente_devoluciones_pendientes(): void
    {
        PaymentRefund::factory()
            ->pending()
            ->createOne();

        PaymentRefund::factory()
            ->completed()
            ->createOne();

        PaymentRefund::factory()
            ->cancelled()
            ->createOne();

        $refunds = $this->repository()->findPending();

        $this->assertCount(1, $refunds);

        $this->assertSame(
            RefundStatus::PENDING,
            $refunds[0]->getStatus()
        );
    }

    public function test_suma_refunds_comprometidos_pending_y_completed(): void
    {
        $reservation = Reservation::factory()->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('10000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->completed()
            ->withAmount('5000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->cancelled()
            ->withAmount('7000.00')
            ->createOne();

        $amount = $this->repository()
            ->sumCommittedByReservation($reservation->id);

        /*
         * PENDING   10.000
         * COMPLETED  5.000
         * CANCELLED   IGNORADO
         */
        $this->assertSame(
            '15000.00',
            $amount
        );
    }

    public function test_suma_solamente_refunds_completados(): void
    {
        $reservation = Reservation::factory()->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('10000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->completed()
            ->withAmount('5000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->completed(PaymentMethod::CASH)
            ->withAmount('3000.00')
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation)
            ->cancelled()
            ->withAmount('9000.00')
            ->createOne();

        $amount = $this->repository()
            ->sumCompletedByReservation($reservation->id);

        $this->assertSame(
            '8000.00',
            $amount
        );
    }

    public function test_guarda_un_refund(): void
    {
        $reservation = Reservation::factory()->createOne();

        $domainRefund = new \App\Domain\Payments\Entities\PaymentRefund(
            id: null,
            reservationId: $reservation->id,
            paymentId: null,
            amount: '20000.00',
            status: RefundStatus::PENDING,
            reason: 'Cancelación administrativa',
            method: null,
            notes: null,
            createdByUserId: null,
            completedByUserId: null,
            completedAt: null,
        );

        $saved = $this->repository()->save(
            $domainRefund
        );

        $this->assertNotNull(
            $saved->getId()
        );

        $this->assertSame(
            RefundStatus::PENDING,
            $saved->getStatus()
        );

        $this->assertDatabaseHas('payment_refunds', [
            'id' => $saved->getId(),
            'reservation_id' => $reservation->id,
            'amount' => '20000.00',
            'status' => RefundStatus::PENDING->value,
        ]);
    }

    public function test_actualiza_un_refund_completado(): void
    {
        $refundModel = PaymentRefund::factory()
            ->pending()
            ->withAmount('20000.00')
            ->createOne();

        $refund = $this->repository()->findById(
            $refundModel->id
        );

        $this->assertNotNull($refund);

        $refund->complete(
            method: PaymentMethod::TRANSFER,
            completedByUserId: $refundModel->created_by_user_id,
            notes: 'Transferencia realizada',
        );

        $updated = $this->repository()->update(
            $refund
        );

        $this->assertSame(
            RefundStatus::COMPLETED,
            $updated->getStatus()
        );

        $this->assertSame(
            PaymentMethod::TRANSFER,
            $updated->getMethod()
        );

        $this->assertNotNull(
            $updated->getCompletedAt()
        );

        $this->assertDatabaseHas('payment_refunds', [
            'id' => $refundModel->id,
            'status' => RefundStatus::COMPLETED->value,
            'method' => PaymentMethod::TRANSFER->value,
            'notes' => 'Transferencia realizada',
        ]);
    }

    private function repository(): PaymentRefundRepository
    {
        return $this->app->make(
            PaymentRefundRepository::class
        );
    }
}
