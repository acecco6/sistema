<?php

namespace App\Domain\Reservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class InvalidReservationStatusException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Estado de reserva inválido para esta operación.', 422);
    }
}
