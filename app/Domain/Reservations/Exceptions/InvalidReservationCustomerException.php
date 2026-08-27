<?php

namespace App\Domain\Reservations\Exceptions;

use App\Shared\Exceptions\DomainException;

final class InvalidReservationCustomerException extends DomainException
{
    public function __construct(
        string $message = 'Cliente de reserva inválido.'
    ) {
        parent::__construct(
            $message,
            422
        );
    }
}
