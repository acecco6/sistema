<?php

namespace App\Application\Reservations\Customer;

final readonly class ShowCustomerReservationQuery
{
    public function __construct(
        public int $reservationId,
        public int $customerUserId,
    ) {}
}
