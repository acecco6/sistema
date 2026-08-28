<?php

namespace App\Application\Reservations\Customer;

final readonly class GetCustomerReservationsQuery
{
    public function __construct(
        public int $customerUserId,
    ) {}
}
