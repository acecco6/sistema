<?php

namespace App\Domain\Courts\Exceptions;

use App\Shared\Exceptions\DomainException;

final class CourtInactiveException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct("La cancha con ID {$id} se encuentra inactiva.", 409);
    }
}
