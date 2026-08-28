<?php

namespace App\Application\Reservations\Guest;

use DateTimeImmutable;

final readonly class CancelGuestReservationCommand
{
    public function __construct(
        public string $publicToken,
        public ?DateTimeImmutable $cancelledAt = null,
    ) {}
}
