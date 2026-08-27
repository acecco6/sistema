<?php

namespace App\Domain\Reservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class InvalidReservationStatusTransitionException extends DomainException
{
    public function __construct(
        string $message = 'Cambio de estado de reserva no permitido.'
    ) {
        parent::__construct(
            $message,
            409
        );
    }
}
