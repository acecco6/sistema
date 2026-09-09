<?php

namespace App\Application\Reservations\FixedReservations\DTOs;

use App\Domain\Reservations\FixedReservations\Entities\FixedReservation;
use App\Domain\Reservations\FixedReservations\Entities\FixedReservationSlot;
use App\Domain\Users\Repositories\UserRepository;

final readonly class FixedReservationDtoFactory
{
    public function __construct(private UserRepository $users) {}

    /** @param FixedReservationSlot[] $slots */
    public function create(FixedReservation $fixedReservation, array $slots): FixedReservationDto
    {
        $customer = $fixedReservation->getCustomerUserId() !== null
            ? $this->users->findById($fixedReservation->getCustomerUserId())
            : null;

        return FixedReservationDto::fromDomain($fixedReservation, $slots, $customer);
    }
}
