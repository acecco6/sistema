<?php

namespace App\Domain\Branches\Exceptions;

use App\Shared\Exceptions\DomainException;

final class BranchAlreadyInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'La sucursal ya se encuentra inactiva.',
            400
        );
    }
}
