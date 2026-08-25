<?php

namespace App\Shared\Exceptions;

final class AuthorizationDeniedException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'No tenés permisos para realizar esta acción.',
            403
        );
    }
}
