<?php

namespace App\Domain\Payments\Entities;

use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Payments\Exceptions\InvalidRefundStatusTransitionException;
use DateTimeImmutable;

final class PaymentRefund
{
    public function __construct(
        private ?int $id,
        private int $reservationId,
        private ?int $paymentId,
        private string $amount,
        private RefundStatus $status,
        private ?string $reason,
        private ?PaymentMethod $method,
        private ?string $notes,
        private ?int $createdByUserId,
        private ?int $completedByUserId,
        private ?DateTimeImmutable $completedAt,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReservationId(): int
    {
        return $this->reservationId;
    }

    public function getPaymentId(): ?int
    {
        return $this->paymentId;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getStatus(): RefundStatus
    {
        return $this->status;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getMethod(): ?PaymentMethod
    {
        return $this->method;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedByUserId(): ?int
    {
        return $this->createdByUserId;
    }

    public function getCompletedByUserId(): ?int
    {
        return $this->completedByUserId;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function complete(
        PaymentMethod $method,
        int $completedByUserId,
        ?string $notes = null,
        ?DateTimeImmutable $completedAt = null,
    ): void {
        if ($this->status !== RefundStatus::PENDING) {
            throw new InvalidRefundStatusTransitionException(
                currentStatus: $this->status,
                targetStatus: RefundStatus::COMPLETED,
            );
        }

        $this->status = RefundStatus::COMPLETED;
        $this->method = $method;
        $this->completedByUserId = $completedByUserId;
        $this->notes = $notes;
        $this->completedAt = $completedAt ?? new DateTimeImmutable();
    }

    public function cancel(): void
    {
        if ($this->status !== RefundStatus::PENDING) {
            throw new InvalidRefundStatusTransitionException(
                currentStatus: $this->status,
                targetStatus: RefundStatus::CANCELLED,
            );
        }

        $this->status = RefundStatus::CANCELLED;
    }
}
