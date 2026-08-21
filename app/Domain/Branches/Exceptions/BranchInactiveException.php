<?php

namespace App\Domain\Branches\Exceptions;

use App\Shared\Exceptions\DomainException;

final class BranchInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: "La sucursal está inactiva.",
            code: 403,
        );
    }
}
