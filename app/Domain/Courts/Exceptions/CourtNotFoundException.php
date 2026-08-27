<?php

namespace App\Domain\Courts\Exceptions;

use App\Shared\Exceptions\DomainException;

final class CourtNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct("La cancha no existe.", 404);
    }
}
