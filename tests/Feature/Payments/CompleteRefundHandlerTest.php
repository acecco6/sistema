<?php

namespace Tests\Feature\Payments;

use App\Application\Payments\Refunds\CompleteRefund\CompleteRefundCommand;
use App\Application\Payments\Refunds\CompleteRefund\CompleteRefundHandler;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Payments\Exceptions\InvalidRefundStatusTransitionException;
use App\Domain\Payments\Exceptions\PaymentRefundNotFoundException;
use App\Models\PaymentRefund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompleteRefundHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_completa_un_refund_pendiente(): void
    {
        $user = User::factory()->createOne();

        $refund = PaymentRefund::factory()
            ->pending()
            ->withAmount('25000.00')
            ->createOne();

        $result = $this->handler()->handle(
            new CompleteRefundCommand(
                refundId: $refund->id,
                method: PaymentMethod::TRANSFER,
                completedByUserId: $user->id,
                notes: 'Transferencia realizada',
            )
        );

        $this->assertSame(
            RefundStatus::COMPLETED->value,
            $result->status
        );

        $this->assertSame(
            PaymentMethod::TRANSFER->value,
            $result->method
        );

        $this->assertSame(
            $user->id,
            $result->completedByUserId
        );

        $this->assertSame(
            'Transferencia realizada',
            $result->notes
        );

        $this->assertNotNull(
            $result->completedAt
        );

        $this->assertDatabaseHas('payment_refunds', [
            'id' => $refund->id,
            'status' => RefundStatus::COMPLETED->value,
            'method' => PaymentMethod::TRANSFER->value,
            'completed_by_user_id' => $user->id,
            'notes' => 'Transferencia realizada',
        ]);
    }

    public function test_completa_refund_con_metodo_cash(): void
    {
        $user = User::factory()->createOne();

        $refund = PaymentRefund::factory()
            ->pending()
            ->createOne();

        $result = $this->handler()->handle(
            new CompleteRefundCommand(
                refundId: $refund->id,
                method: PaymentMethod::CASH,
                completedByUserId: $user->id,
            )
        );

        $this->assertSame(
            RefundStatus::COMPLETED->value,
            $result->status
        );

        $this->assertSame(
            PaymentMethod::CASH->value,
            $result->method
        );
    }

    public function test_no_permite_completar_un_refund_dos_veces(): void
    {
        $user = User::factory()->createOne();

        $refund = PaymentRefund::factory()
            ->completed()
            ->createOne();

        $this->expectException(
            InvalidRefundStatusTransitionException::class
        );

        $this->handler()->handle(
            new CompleteRefundCommand(
                refundId: $refund->id,
                method: PaymentMethod::TRANSFER,
                completedByUserId: $user->id,
            )
        );
    }

    public function test_no_permite_completar_un_refund_cancelado(): void
    {
        $user = User::factory()->createOne();

        $refund = PaymentRefund::factory()
            ->cancelled()
            ->createOne();

        $this->expectException(
            InvalidRefundStatusTransitionException::class
        );

        $this->handler()->handle(
            new CompleteRefundCommand(
                refundId: $refund->id,
                method: PaymentMethod::TRANSFER,
                completedByUserId: $user->id,
            )
        );
    }

    public function test_lanza_not_found_si_el_refund_no_existe(): void
    {
        $user = User::factory()->createOne();

        $this->expectException(
            PaymentRefundNotFoundException::class
        );

        $this->handler()->handle(
            new CompleteRefundCommand(
                refundId: 999999,
                method: PaymentMethod::TRANSFER,
                completedByUserId: $user->id,
            )
        );
    }

    public function test_completed_at_se_registra_al_completar(): void
    {
        $user = User::factory()->createOne();

        $refund = PaymentRefund::factory()
            ->pending()
            ->createOne();

        $result = $this->handler()->handle(
            new CompleteRefundCommand(
                refundId: $refund->id,
                method: PaymentMethod::OTHER,
                completedByUserId: $user->id,
            )
        );

        $this->assertNotNull(
            $result->completedAt
        );

        $refund->refresh();

        $this->assertNotNull(
            $refund->completed_at
        );
    }

    private function handler(): CompleteRefundHandler
    {
        return $this->app->make(
            CompleteRefundHandler::class
        );
    }
}
