<?php

namespace App\Domain\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;

final class InvalidPaymentAmountException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'El monto del pago debe ser mayor a cero.',
            code: 422,
        );
    }
}
