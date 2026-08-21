<?php

namespace App\Domain\Memberships\Exceptions;

use App\Shared\Exceptions\DomainException;

final class MembershipAlreadyExistsException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'El usuario ya posee una membresía para este alcance.',
            code: 409,
        );
    }
}
