<?php

namespace App\Domain\Pricing\Exceptions;

use App\Shared\Exceptions\DomainException;

final class CourtPriceNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct('No se encontró el precio', 404);
    }
}
