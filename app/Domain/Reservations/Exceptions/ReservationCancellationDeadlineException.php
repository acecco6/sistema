<?php

namespace App\Domain\Reservations\Exceptions;

use App\Shared\Exceptions\DomainException;

class ReservationCancellationDeadlineException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'La reserva solo puede cancelarse con al menos 24 horas de anticipación.',
            code: 422
        );
    }
}
