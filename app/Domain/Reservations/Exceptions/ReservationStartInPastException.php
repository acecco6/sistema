<?php

namespace App\Domain\Reservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class ReservationStartInPastException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'El horario seleccionado no es válido',
            code: 422,
        );
    }
}
