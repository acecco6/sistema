<?php

namespace App\Application\Reservations\FixedReservations\Create;

final readonly class CreateFixedReservationSlotData
{
    public function __construct(
        public int $courtId,
        public int $dayOfWeek,
        public string $startTime,
        public int $durationMinutes,
    ) {}
}
