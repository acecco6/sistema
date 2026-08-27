<?php

namespace App\Domain\Pricing\Exceptions;

use App\Shared\Exceptions\DomainException;

final class CourtPriceRuleNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct('La regla de precio no existe', 404);
    }
}
