<?php

namespace App\Domain\Reservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class CourtNotAvailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'La cancha no está disponible en el horario solicitado.',
            409
        );
    }
}
