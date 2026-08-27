<?php

namespace App\Domain\Reservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class InvalidReservationTimeException extends DomainException
{
    public function __construct(
        string $message = 'El horario de la reserva no es válido.'
    ) {
        parent::__construct(
            $message,
            422
        );
    }
}
