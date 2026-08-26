<?php

namespace App\Domain\Courts\Exceptions;

use App\Shared\Exceptions\DomainException;

final class TipoCourtNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct('El tipo de cancha no existe.', 404);
    }
}
