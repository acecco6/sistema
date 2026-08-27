<?php

namespace App\Domain\Reservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class InvalidReservationDurationException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'La duración de la reserva no respeta el intervalo permitido para este tipo de cancha.',
            422
        );
    }
}
