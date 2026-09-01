<?php

namespace App\Application\Payments\DTOs;

use App\Domain\Payments\Entities\Payment;

final readonly class PaymentDto
{
    public function __construct(
        public ?int $id,
        public int $reservationId,
        public string $amount,
        public string $method,
        public string $status,
        public ?string $provider,
        public ?string $providerPaymentId,
        public string $externalReference,
        public ?int $createdByUserId,
        public ?string $paidAt,
    ) {}

    public static function fromDomain(Payment $payment): self
    {
        return new self(
            id: $payment->getId(),
            reservationId: $payment->getReservationId(),
            amount: $payment->getAmount(),
            method: $payment->getMethod()->value,
            status: $payment->getStatus()->value,
            provider: $payment->getProvider(),
            providerPaymentId: $payment->getProviderPaymentId(),
            externalReference: $payment->getExternalReference(),
            createdByUserId: $payment->getCreatedByUserId(),
            paidAt: $payment->getPaidAt()?->format('Y-m-d H:i:s'),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reservation_id' => $this->reservationId,
            'amount' => $this->amount,
            'method' => $this->method,
            'status' => $this->status,
            'provider' => $this->provider,
            'provider_payment_id' => $this->providerPaymentId,
            'external_reference' => $this->externalReference,
            'created_by_user_id' => $this->createdByUserId,
            'paid_at' => $this->paidAt,
        ];
    }
}
