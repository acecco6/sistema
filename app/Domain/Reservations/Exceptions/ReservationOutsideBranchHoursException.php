<?php

namespace App\Domain\Reservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class ReservationOutsideBranchHoursException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'La reserva está fuera del horario de atención de la sucursal.',
            422
        );
    }
}
