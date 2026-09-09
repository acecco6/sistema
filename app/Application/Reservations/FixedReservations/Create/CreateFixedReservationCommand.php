<?php

namespace App\Application\Reservations\FixedReservations\Create;

use App\Application\Reservations\FixedReservations\Create\CreateFixedReservationSlotData;
use DateTimeImmutable;

final readonly class CreateFixedReservationCommand
{
    /**
     * @param CreateFixedReservationSlotData[] $slots
     */
    public function __construct(
        public int $clubId,

        public ?int $customerUserId,

        public ?string $guestName,
        public ?string $guestEmail,
        public ?string $guestPhone,

        public int $createdByUserId,

        public DateTimeImmutable $startsOn,
        public ?DateTimeImmutable $endsOn,

        public ?string $notes,

        public array $slots,
        public ?int $clubCustomerId = null,
    ) {}
}
