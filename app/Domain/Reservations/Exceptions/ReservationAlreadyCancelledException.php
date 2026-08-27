<?php

namespace App\Domain\Reservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class ReservationAlreadyCancelledException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'La reserva ya se encuentra cancelada.',
            409
        );
    }
}
