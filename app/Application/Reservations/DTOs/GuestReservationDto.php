<?php

namespace App\Application\Reservations\DTOs;

use App\Domain\Reservations\Entities\Reservation;

final readonly class GuestReservationDto
{
    public function __construct(
        public int $courtId,
        public ?string $guestName,
        public string $startsAt,
        public string $endsAt,
        public string $totalPrice,
        public string $status,
        public ?string $notes,
        public ?string $cancelledAt,
    ) {}

    public static function fromDomain(Reservation $reservation): self
    {
        return new self(
            courtId: $reservation->getCourtId(),
            guestName: $reservation->getGuestName(),
            startsAt: $reservation->getStartsAt()->format('Y-m-d H:i:s'),
            endsAt: $reservation->getEndsAt()->format('Y-m-d H:i:s'),
            totalPrice: $reservation->getTotalPrice(),
            status: $reservation->getStatus()->value,
            notes: $reservation->getNotes(),
            cancelledAt: $reservation->getCancelledAt()?->format('Y-m-d H:i:s'),
        );
    }

    public function toArray(): array
    {
        return [
            'court_id' => $this->courtId,
            'guest_name' => $this->guestName,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'total_price' => $this->totalPrice,
            'status' => $this->status,
            'notes' => $this->notes,
            'cancelled_at' => $this->cancelledAt,
        ];
    }
}
