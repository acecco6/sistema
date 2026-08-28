<?php

namespace App\Application\Reservations\Cancel;

use DateTimeImmutable;

final readonly class CancelCustomerReservationCommand
{
    public function __construct(
        public int $reservationId,
        public int $customerUserId,
        public ?DateTimeImmutable $cancelledAt = null,
    ) {}
}
