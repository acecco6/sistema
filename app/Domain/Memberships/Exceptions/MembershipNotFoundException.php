<?php

namespace App\Domain\Memberships\Exceptions;

use App\Shared\Exceptions\DomainException;

final class MembershipNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: "Membresía no encontrada.",
            code: 404,
        );
    }
}
