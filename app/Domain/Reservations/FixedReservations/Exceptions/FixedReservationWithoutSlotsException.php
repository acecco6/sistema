<?php

namespace App\Domain\Reservations\FixedReservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class FixedReservationWithoutSlotsException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'La reserva fija debe contener al menos un horario.',
            422
        );
    }
}
