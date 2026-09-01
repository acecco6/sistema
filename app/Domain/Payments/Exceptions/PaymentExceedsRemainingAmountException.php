<?php

namespace App\Domain\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;

final class PaymentExceedsRemainingAmountException extends DomainException
{
    public function __construct(
        string $amount,
        string $remainingAmount,
    ) {
        parent::__construct(
            message: sprintf(
                'El monto del pago (%s) supera el saldo pendiente (%s).',
                $amount,
                $remainingAmount,
            ),
            code: 422,
        );
    }
}
