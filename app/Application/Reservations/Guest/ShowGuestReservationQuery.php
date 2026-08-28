<?php

namespace App\Application\Reservations\Guest;

final readonly class ShowGuestReservationQuery
{
    public function __construct(
        public string $publicToken,
    ) {}
}
