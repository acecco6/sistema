<?php

namespace App\Domain\Clubs\Exceptions;

use App\Shared\Exceptions\DomainException;

final class ClubInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'El club está inactivo.',
            code: 403,
        );
    }
}
