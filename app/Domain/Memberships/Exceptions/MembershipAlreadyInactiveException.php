<?php

namespace App\Domain\Memberships\Exceptions;

use App\Shared\Exceptions\DomainException;

final class MembershipAlreadyInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: "La membresía ya se encuentra inactiva",
            code: 409
        );
    }
}
