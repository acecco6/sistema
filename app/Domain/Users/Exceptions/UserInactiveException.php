<?php

namespace App\Domain\Users\Exceptions;

use App\Shared\Exceptions\DomainException;

final class UserInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: "El usuario se encuentra inactivo.",
            code: 403,
        );
    }
}
