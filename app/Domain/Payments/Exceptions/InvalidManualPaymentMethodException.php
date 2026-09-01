<?php

namespace App\Domain\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;

final class InvalidManualPaymentMethodException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Mercado Pago no puede registrarse como pago manual.',
            code: 422,
        );
    }
}
