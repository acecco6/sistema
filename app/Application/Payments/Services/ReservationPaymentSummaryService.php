<?php

namespace App\Application\Payments\Services;

use App\Application\Payments\DTOs\ReservationPaymentSummary;
use App\Domain\Payments\Enums\FinancialStatus;
use App\Domain\Payments\Repositories\PaymentRefundRepository;
use App\Domain\Payments\Repositories\PaymentRepository;
use App\Domain\Payments\Services\ReservationPaymentPolicy;
use App\Domain\Reservations\Entities\Reservation;

final readonly class ReservationPaymentSummaryService
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private PaymentRefundRepository $refundRepository,
        private ReservationPaymentPolicy $paymentPolicy,
    ) {}

    public function calculate(
        Reservation $reservation,
    ): ReservationPaymentSummary {

        /*
         * Total histórico de pagos aprobados.
         */
        $approved = $this->paymentRepository
            ->sumApprovedByReservation(
                $reservation->getId()
            );

        /*
         * Solamente contamos devoluciones COMPLETED.
         *
         * Un refund PENDING todavía representa dinero
         * que no fue efectivamente devuelto.
         */
        $refunded = $this->refundRepository
            ->sumCompletedByReservation(
                $reservation->getId()
            );

        /*
         * Dinero que efectivamente quedó cobrado.
         *
         * approved - refunded
         */
        $netPaid = bcsub(
            $approved,
            $refunded,
            2
        );

        /*
         * Protección ante datos históricos inconsistentes.
         *
         * Nunca exponemos un pago neto negativo.
         */
        if (bccomp($netPaid, '0.00', 2) < 0) {
            $netPaid = '0.00';
        }

        /*
         * La regla de cuánto se necesita como seña
         * pertenece a ReservationPaymentPolicy.
         */
        $deposit = $this->paymentPolicy->requiredDeposit(
            $reservation->getTotalPrice()
        );

        /*
         * Lo que todavía falta cobrar debe calcularse
         * sobre el dinero neto que quedó pagado.
         */
        $remaining = bcsub(
            $reservation->getTotalPrice(),
            $netPaid,
            2
        );

        if (bccomp($remaining, '0.00', 2) < 0) {
            $remaining = '0.00';
        }

        return new ReservationPaymentSummary(
            totalPrice: $reservation->getTotalPrice(),
            approvedAmount: $approved,
            requiredDeposit: $deposit,
            refundedAmount: $refunded,
            netPaidAmount: $netPaid,
            remainingAmount: $remaining,
            financialStatus: $this->resolveStatus(
                total: $reservation->getTotalPrice(),
                netPaid: $netPaid,
                deposit: $deposit,
            ),
        );
    }

    private function resolveStatus(
        string $total,
        string $netPaid,
        string $deposit,
    ): FinancialStatus {

        if (bccomp($netPaid, '0.00', 2) === 0) {
            return FinancialStatus::UNPAID;
        }

        if (bccomp($netPaid, $total, 2) === 1) {
            return FinancialStatus::OVERPAID;
        }

        if (bccomp($netPaid, $total, 2) === 0) {
            return FinancialStatus::PAID;
        }

        if (bccomp($netPaid, $deposit, 2) >= 0) {
            return FinancialStatus::DEPOSIT_PAID;
        }

        return FinancialStatus::PARTIALLY_PAID;
    }
}
