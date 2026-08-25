<?php

namespace App\Domain\Courts\Exceptions;

use App\Shared\Exceptions\DomainException;

final class CourtNotFoundException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct("La cancha con ID {$id} no existe.", 404);
    }
}
