<?php

namespace App\Domain\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;

final class InvalidRefundAmountException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'El monto de la devolución debe ser mayor a cero.',
            code: 422,
        );
    }
}
