<?php

namespace App\Application\Payments\Services;

use App\Application\Payments\DTOs\ReservationPaymentSummary;
use App\Domain\Payments\Enums\FinancialStatus;
use App\Domain\Payments\Repositories\PaymentRepository;
use App\Domain\Payments\Services\ReservationPaymentPolicy;
use App\Domain\Reservations\Entities\Reservation;

final readonly class ReservationPaymentSummaryService
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private ReservationPaymentPolicy $paymentPolicy,
    ) {}

    public function calculate(
        Reservation $reservation,
    ): ReservationPaymentSummary {

        $approved = $this->paymentRepository
            ->sumApprovedByReservation(
                $reservation->getId()
            );

        /*
         * La regla de cuánto se necesita como seña
         * pertenece a ReservationPaymentPolicy.
         */
        $deposit = $this->paymentPolicy->requiredDeposit(
            $reservation->getTotalPrice()
        );

        $remaining = bcsub(
            $reservation->getTotalPrice(),
            $approved,
            2
        );

        if (bccomp($remaining, '0.00', 2) < 0) {
            $remaining = '0.00';
        }

        return new ReservationPaymentSummary(
            totalPrice: $reservation->getTotalPrice(),
            approvedAmount: $approved,
            requiredDeposit: $deposit,
            remainingAmount: $remaining,
            financialStatus: $this->resolveStatus(
                total: $reservation->getTotalPrice(),
                approved: $approved,
                deposit: $deposit,
            ),
        );
    }

    private function resolveStatus(
        string $total,
        string $approved,
        string $deposit,
    ): FinancialStatus {

        if (bccomp($approved, '0', 2) === 0) {
            return FinancialStatus::UNPAID;
        }

        if (bccomp($approved, $total, 2) === 1) {
            return FinancialStatus::OVERPAID;
        }

        if (bccomp($approved, $total, 2) === 0) {
            return FinancialStatus::PAID;
        }

        if (bccomp($approved, $deposit, 2) >= 0) {
            return FinancialStatus::DEPOSIT_PAID;
        }

        return FinancialStatus::PARTIALLY_PAID;
    }
}
