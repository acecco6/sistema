<?php

namespace App\Application\Reservations\Cancel;

use DateTimeImmutable;

final readonly class CancelReservationCommand
{
    public function __construct(
        public int $id,
        public ?DateTimeImmutable $cancelledAt = null,
        public bool $createRefund = false,
        public ?string $refundReason = null,
        public ?int $cancelledByUserId = null,
    ) {}
}
