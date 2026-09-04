<?php

namespace App\Domain\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;

final class RefundExceedsRefundableAmountException extends DomainException
{
    public function __construct(
        string $amount,
        string $refundableAmount,
    ) {
        parent::__construct(
            message: "El monto de la devolución ({$amount}) supera el monto disponible para devolver ({$refundableAmount}).",
            code: 422,
        );
    }
}
