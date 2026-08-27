<?php

namespace App\Application\Reservations\Create;

use DateTimeImmutable;

final readonly class CreateReservationCommand
{
    public function __construct(
        public int $courtId,

        public ?int $customerUserId,
        public ?int $createdByUserId,

        public ?string $guestName,
        public ?string $guestEmail,
        public ?string $guestPhone,

        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,

        public ?string $notes,

        /*
         * El controller decide el estado inicial
         * según quién crea la reserva.
         */
        public bool $confirmed = false,
    ) {}
}
