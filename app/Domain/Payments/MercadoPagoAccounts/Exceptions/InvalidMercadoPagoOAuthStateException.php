<?php

namespace App\Domain\Payments\MercadoPagoAccounts\Exceptions;

use App\Shared\Exceptions\DomainException;

final class InvalidMercadoPagoOAuthStateException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'El estado OAuth de Mercado Pago es inválido o expiró.',
            code: 400,
        );
    }
}
