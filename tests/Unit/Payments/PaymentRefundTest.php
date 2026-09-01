<?php

namespace Tests\Unit\Payments;

use App\Domain\Payments\Entities\PaymentRefund;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Payments\Exceptions\InvalidRefundStatusTransitionException;
use PHPUnit\Framework\TestCase;

final class PaymentRefundTest extends TestCase
{
    public function test_refund_pending_puede_completarse(): void
    {
        $refund = $this->createRefund();

        $refund->complete(
            method: PaymentMethod::TRANSFER,
            completedByUserId: 10,
            notes: 'Transferencia realizada',
        );

        $this->assertSame(
            RefundStatus::COMPLETED,
            $refund->getStatus()
        );

        $this->assertSame(
            PaymentMethod::TRANSFER,
            $refund->getMethod()
        );

        $this->assertSame(
            10,
            $refund->getCompletedByUserId()
        );

        $this->assertSame(
            'Transferencia realizada',
            $refund->getNotes()
        );

        $this->assertNotNull(
            $refund->getCompletedAt()
        );
    }

    public function test_refund_pending_puede_cancelarse(): void
    {
        $refund = $this->createRefund();

        $refund->cancel();

        $this->assertSame(
            RefundStatus::CANCELLED,
            $refund->getStatus()
        );
    }

    public function test_refund_completado_no_puede_completarse_nuevamente(): void
    {
        $refund = $this->createRefund();

        $refund->complete(
            method: PaymentMethod::TRANSFER,
            completedByUserId: 10,
        );

        $this->expectException(
            InvalidRefundStatusTransitionException::class
        );

        $refund->complete(
            method: PaymentMethod::CASH,
            completedByUserId: 20,
        );
    }

    public function test_refund_cancelado_no_puede_completarse(): void
    {
        $refund = $this->createRefund();

        $refund->cancel();

        $this->expectException(
            InvalidRefundStatusTransitionException::class
        );

        $refund->complete(
            method: PaymentMethod::TRANSFER,
            completedByUserId: 10,
        );
    }

    public function test_refund_completado_no_puede_cancelarse(): void
    {
        $refund = $this->createRefund();

        $refund->complete(
            method: PaymentMethod::TRANSFER,
            completedByUserId: 10,
        );

        $this->expectException(
            InvalidRefundStatusTransitionException::class
        );

        $refund->cancel();
    }

    public function test_refund_cancelado_no_puede_cancelarse_nuevamente(): void
    {
        $refund = $this->createRefund();

        $refund->cancel();

        $this->expectException(
            InvalidRefundStatusTransitionException::class
        );

        $refund->cancel();
    }

    private function createRefund(): PaymentRefund
    {
        return new PaymentRefund(
            id: 1,
            reservationId: 100,
            paymentId: null,
            amount: '20000.00',
            status: RefundStatus::PENDING,
            reason: 'Cancelación administrativa',
            method: null,
            notes: null,
            createdByUserId: 5,
            completedByUserId: null,
            completedAt: null,
        );
    }
}
