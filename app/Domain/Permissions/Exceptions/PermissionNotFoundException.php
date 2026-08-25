<?php

namespace App\Domain\Permissions\Exceptions;

use App\Shared\Exceptions\DomainException;

final class PermissionNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Permiso no encontrado', 404);
    }
}
