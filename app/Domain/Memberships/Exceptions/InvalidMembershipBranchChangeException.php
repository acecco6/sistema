<?php

namespace App\Domain\Memberships\Exceptions;

use App\Shared\Exceptions\DomainException;

final class InvalidMembershipBranchChangeException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'La membresía no puede cambiarse a una sucursal diferente.',
            code: 422
        );
    }
}
