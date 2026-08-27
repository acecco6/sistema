<?php

namespace App\Application\Reservations\DTOs;

use App\Domain\Reservations\Entities\Reservation;

final readonly class ReservationDto
{
    public function __construct(
        public ?int $id,
        public int $courtId,

        public ?int $customerUserId,
        public ?int $createdByUserId,

        public ?string $guestName,
        public ?string $guestEmail,
        public ?string $guestPhone,

        public string $startsAt,
        public string $endsAt,

        public string $totalPrice,
        public string $status,

        public string $publicToken,

        public ?string $notes,
        public ?string $cancelledAt,
    ) {}

    public static function fromDomain(Reservation $reservation): self
    {
        return new self(
            id: $reservation->getId(),
            courtId: $reservation->getCourtId(),
            customerUserId: $reservation->getCustomerUserId(),
            createdByUserId: $reservation->getCreatedByUserId(),
            guestName: $reservation->getGuestName(),
            guestEmail: $reservation->getGuestEmail(),
            guestPhone: $reservation->getGuestPhone(),
            startsAt: $reservation->getStartsAt()->format('Y-m-d H:i:s'),
            endsAt: $reservation->getEndsAt()->format('Y-m-d H:i:s'),
            totalPrice: $reservation->getTotalPrice(),
            status: $reservation->getStatus()->value,
            publicToken: $reservation->getPublicToken(),
            notes: $reservation->getNotes(),
            cancelledAt: $reservation->getCancelledAt()?->format('Y-m-d H:i:s'),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'court_id' => $this->courtId,
            'customer_user_id' => $this->customerUserId,
            'created_by_user_id' => $this->createdByUserId,
            'guest_name' => $this->guestName,
            'guest_email' => $this->guestEmail,
            'guest_phone' => $this->guestPhone,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'total_price' => $this->totalPrice,
            'status' => $this->status,
            'public_token' => $this->publicToken,
            'notes' => $this->notes,
            'cancelled_at' => $this->cancelledAt,
        ];
    }
}
