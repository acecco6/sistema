<?php

namespace App\Domain\Memberships\Exceptions;

use App\Shared\Exceptions\DomainException;

final class MembershipScopeConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'El usuario ya posee una membresía incompatible con el alcance solicitado.',
            409
        );
    }
}
