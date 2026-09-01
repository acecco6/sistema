<?php

namespace App\Domain\Payments\Repositories;

use App\Domain\Payments\Entities\PaymentRefund;

interface PaymentRefundRepository
{
    public function findById(int $id): ?PaymentRefund;

    public function findByIdForUpdate(int $id): ?PaymentRefund;

    /**
     * @return PaymentRefund[]
     */
    public function findByReservation(int $reservationId): array;

    /**
     * @return PaymentRefund[]
     */
    public function findPending(): array;

    public function save(PaymentRefund $refund): PaymentRefund;

    public function update(PaymentRefund $refund): PaymentRefund;

    /**
     * Suma las devoluciones que ya están comprometidas.
     *
     * PENDING:
     *   dinero que todavía debe devolverse.
     *
     * COMPLETED:
     *   dinero que ya fue devuelto.
     *
     * CANCELLED no participa del cálculo.
     */
    public function sumCommittedByReservation(int $reservationId): string;

    /**
     * Suma únicamente dinero cuya devolución
     * ya fue confirmada.
     */
    public function sumCompletedByReservation(int $reservationId): string;
}
