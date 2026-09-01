<?php

namespace App\Application\Payments\GetReservationPayments;

final readonly class GetReservationPaymentsQuery
{
    public function __construct(
        public int $reservationId,
    ) {}
}
