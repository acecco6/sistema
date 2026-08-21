<?php

namespace App\Domain\Clubs\Exceptions;

use App\Shared\Exceptions\DomainException;

final class ClubAlreadyInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'El club ya se encuentra inactivo.',
            400
        );
    }
}
