<?php

namespace App\Domain\Pricing\Exceptions;

use App\Shared\Exceptions\DomainException;

final class PriceNotAvailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct('No hay precio disponible para la fecha y hora solicitadas', 404);
    }
}
