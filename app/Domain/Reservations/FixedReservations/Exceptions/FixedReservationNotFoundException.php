<?php

namespace App\Domain\Reservations\FixedReservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class FixedReservationNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'No se encontró la reserva fija.',
            404
        );
    }
}
