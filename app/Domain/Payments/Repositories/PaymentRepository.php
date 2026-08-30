<?php

namespace App\Domain\Payments\Repositories;

use App\Domain\Payments\Entities\Payment;

interface PaymentRepository
{
    public function findById(int $id): ?Payment;

    public function findByExternalReference(string $externalReference): ?Payment;

    public function findByProviderPaymentId(string $providerPaymentId): ?Payment;

    /**
     * @return Payment[]
     */
    public function findByReservation(int $reservationId): array;

    public function save(Payment $payment): Payment;

    public function update(Payment $payment): Payment;

    public function sumApprovedByReservation(int $reservationId): string;

    public function findPendingByReservation(int $reservationId): ?Payment;
}
