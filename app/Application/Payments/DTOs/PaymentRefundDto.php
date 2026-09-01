<?php

namespace App\Application\Payments\DTOs;

use App\Domain\Payments\Entities\PaymentRefund;

final readonly class PaymentRefundDto
{
    public function __construct(
        public int $id,
        public int $reservationId,
        public ?int $paymentId,
        public string $amount,
        public string $status,
        public ?string $reason,
        public ?string $method,
        public ?string $notes,
        public ?int $createdByUserId,
        public ?int $completedByUserId,
        public ?string $completedAt,
    ) {}

    public static function fromDomain(PaymentRefund $refund): self
    {
        return new self(
            id: $refund->getId(),
            reservationId: $refund->getReservationId(),
            paymentId: $refund->getPaymentId(),
            amount: $refund->getAmount(),
            status: $refund->getStatus()->value,
            reason: $refund->getReason(),
            method: $refund->getMethod()?->value,
            notes: $refund->getNotes(),
            createdByUserId: $refund->getCreatedByUserId(),
            completedByUserId: $refund->getCompletedByUserId(),
            completedAt: $refund->getCompletedAt()?->format(DATE_ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reservation_id' => $this->reservationId,
            'payment_id' => $this->paymentId,
            'amount' => $this->amount,
            'status' => $this->status,
            'reason' => $this->reason,
            'method' => $this->method,
            'notes' => $this->notes,
            'created_by_user_id' => $this->createdByUserId,
            'completed_by_user_id' => $this->completedByUserId,
            'completed_at' => $this->completedAt,
        ];
    }
}
