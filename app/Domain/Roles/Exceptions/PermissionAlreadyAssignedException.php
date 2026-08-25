<?php

namespace App\Domain\Roles\Exceptions;

use App\Shared\Exceptions\DomainException;

final class PermissionAlreadyAssignedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('El permiso ya está asignado al rol', 400);
    }
}
