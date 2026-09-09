<?php

namespace App\Domain\Reservations\FixedReservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class FixedReservationScheduleConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct('La cancha ya tiene una reserva fija superpuesta para ese día y horario.', 409);
    }
}
