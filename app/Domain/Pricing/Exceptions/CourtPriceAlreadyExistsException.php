<?php

namespace App\Domain\Pricing\Exceptions;

use App\Shared\Exceptions\DomainException;


class CourtPriceAlreadyExistsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Ya existe un precio registrado para esta sucursal y tipo de cancha.', 409);
    }
}
