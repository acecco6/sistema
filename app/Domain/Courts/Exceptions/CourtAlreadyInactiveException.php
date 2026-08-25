<?php

namespace App\Domain\Courts\Exceptions;

use App\Shared\Exceptions\DomainException;

final class CourtAlreadyInactiveException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct("La cancha con ID {$id} ya se encuentra inactiva.", 409);
    }
}
