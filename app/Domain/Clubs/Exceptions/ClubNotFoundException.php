<?php

namespace App\Domain\Clubs\Exceptions;

use App\Shared\Exceptions\DomainException;



final class ClubNotFoundException extends DomainException
{
    public function __construct(?int $clubId = null)
    {
        parent::__construct(
            $clubId !== null
                ? "El club con ID {$clubId} no existe."
                : 'El club no existe.',
            404
        );
    }
}
