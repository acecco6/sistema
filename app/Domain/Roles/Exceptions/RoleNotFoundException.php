<?php

namespace App\Domain\Roles\Exceptions;

use App\Shared\Exceptions\DomainException;

final class RoleNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Rol no encontrado.',
            code: 404,
        );
    }
}
