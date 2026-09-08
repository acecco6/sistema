<?php

namespace App\Application\Reservations\FixedReservations\DTOs;

use App\Domain\Reservations\FixedReservations\Entities\FixedReservation;
use App\Domain\Reservations\FixedReservations\Entities\FixedReservationSlot;

final readonly class FixedReservationDto
{
    /**
     * @param FixedReservationSlotDto[] $slots
     */
    public function __construct(
        public int $id,
        public int $clubId,
        public ?int $customerUserId,
        public ?string $guestName,
        public ?string $guestEmail,
        public ?string $guestPhone,
        public int $createdByUserId,
        public string $startsOn,
        public ?string $endsOn,
        public bool $active,
        public ?string $notes,
        public array $slots,
    ) {}

    public static function fromDomain(
        FixedReservation $fixedReservation,
        array $slots
    ): self {
        return new self(
            id: $fixedReservation->getId(),
            clubId: $fixedReservation->getClubId(),
            customerUserId: $fixedReservation->getCustomerUserId(),
            guestName: $fixedReservation->getGuestName(),
            guestEmail: $fixedReservation->getGuestEmail(),
            guestPhone: $fixedReservation->getGuestPhone(),
            createdByUserId: $fixedReservation->getCreatedByUserId(),
            startsOn: $fixedReservation
                ->getStartsOn()
                ->format('Y-m-d'),
            endsOn: $fixedReservation
                ->getEndsOn()
                ?->format('Y-m-d'),
            active: $fixedReservation->isActive(),
            notes: $fixedReservation->getNotes(),
            slots: array_map(
                fn(FixedReservationSlot $slot) =>
                FixedReservationSlotDto::fromDomain($slot),
                $slots
            ),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->clubId,
            'customer_user_id' => $this->customerUserId,
            'guest' => $this->customerUserId === null
                ? [
                    'name' => $this->guestName,
                    'email' => $this->guestEmail,
                    'phone' => $this->guestPhone,
                ]
                : null,
            'created_by_user_id' => $this->createdByUserId,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
            'active' => $this->active,
            'notes' => $this->notes,
            'slots' => array_map(
                fn(FixedReservationSlotDto $slot) =>
                $slot->toArray(),
                $this->slots
            ),
        ];
    }
}
