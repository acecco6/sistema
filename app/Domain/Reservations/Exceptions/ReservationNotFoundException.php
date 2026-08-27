<?php

namespace App\Domain\Reservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class ReservationNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Reserva no encontrada.',
            404
        );
    }
}
