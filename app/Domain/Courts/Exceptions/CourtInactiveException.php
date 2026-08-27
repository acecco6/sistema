<?php

namespace App\Domain\Courts\Exceptions;

use App\Shared\Exceptions\DomainException;

final class CourtInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct("La cancha se encuentra inactiva.", 409);
    }
}
