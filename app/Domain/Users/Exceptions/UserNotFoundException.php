<?php

namespace App\Domain\Users\Exceptions;

use App\Shared\Exceptions\DomainException;

final class UserNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Usuario no encontrado.',
            code: 404,
        );
    }
}
