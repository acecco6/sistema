<?php

namespace App\Domain\Payments\Entities;

use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use DateTimeImmutable;

final class Payment
{
    public function __construct(
        private ?int $id,
        private int $reservationId,
        private string $amount,
        private PaymentMethod $method,
        private PaymentStatus $status,
        private ?string $provider,
        private ?string $providerPreferenceId,
        private ?string $providerPaymentId,
        private string $externalReference,
        private ?string $checkoutUrl,
        private ?int $createdByUserId,
        private ?DateTimeImmutable $paidAt,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReservationId(): int
    {
        return $this->reservationId;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function getProviderPreferenceId(): ?string
    {
        return $this->providerPreferenceId;
    }

    public function getProviderPaymentId(): ?string
    {
        return $this->providerPaymentId;
    }

    public function getExternalReference(): string
    {
        return $this->externalReference;
    }

    public function getCheckoutUrl(): ?string
    {
        return $this->checkoutUrl;
    }

    public function getCreatedByUserId(): ?int
    {
        return $this->createdByUserId;
    }

    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function markApproved(
        string $providerPaymentId,
        ?DateTimeImmutable $paidAt = null
    ): void {
        $this->status = PaymentStatus::APPROVED;
        $this->providerPaymentId = $providerPaymentId;
        $this->paidAt = $paidAt ?? new DateTimeImmutable();
    }

    public function markRejected(): void
    {
        $this->status = PaymentStatus::REJECTED;
    }

    public function markCancelled(): void
    {
        $this->status = PaymentStatus::CANCELLED;
    }

    public function markRefunded(): void
    {
        $this->status = PaymentStatus::REFUNDED;
    }

    public function setProviderPaymentId(string $providerPaymentId): void
    {
        $this->providerPaymentId = $providerPaymentId;
    }
}
