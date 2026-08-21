<?php

namespace App\Domain\Memberships\Exceptions;

use App\Shared\Exceptions\DomainException;



final class BranchDoesNotBelongToClubException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'La sucursal no pertenece al club.',
            code: 409
        );
    }
}
