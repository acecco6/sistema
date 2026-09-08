<?php

namespace App\Domain\Reservations\FixedReservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class FixedReservationCourtOutsideClubException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'La cancha no pertenece al club.',
            422
        );
    }
}
