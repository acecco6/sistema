<?php

namespace App\Domain\Payments\Services;

final class ReservationPaymentPolicy
{
    public const REQUIRED_DEPOSIT_PERCENTAGE = 50;

    public function requiredDeposit(
        string $totalPrice
    ): string {
        return bcdiv(
            bcmul(
                $totalPrice,
                (string) self::REQUIRED_DEPOSIT_PERCENTAGE,
                2
            ),
            '100',
            2
        );
    }

    public function isDepositCovered(
        string $totalPrice,
        string $approvedAmount
    ): bool {
        $requiredDeposit = $this->requiredDeposit(
            $totalPrice
        );

        return bccomp(
            $approvedAmount,
            $requiredDeposit,
            2
        ) >= 0;
    }

    public function percentage(): int
    {
        return self::REQUIRED_DEPOSIT_PERCENTAGE;
    }
}
