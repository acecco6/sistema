<?php

namespace App\Application\Reservations\DTOs;

use App\Domain\Reservations\Entities\Reservation;
use App\Domain\Users\Repositories\UserRepository;

final readonly class ReservationDtoFactory
{
    public function __construct(private UserRepository $users) {}

    public function create(Reservation $reservation): ReservationDto
    {
        $customer = $reservation->getCustomerUserId() !== null
            ? $this->users->findById($reservation->getCustomerUserId())
            : null;

        return ReservationDto::fromDomain($reservation, $customer);
    }

    /** @param Reservation[] $reservations @return ReservationDto[] */
    public function createMany(array $reservations): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn (Reservation $reservation) => $reservation->getCustomerUserId(),
            $reservations,
        ))));

        $customers = [];
        foreach ($this->users->findByIds($ids) as $user) {
            $customers[$user->id()] = $user;
        }

        return array_map(
            fn (Reservation $reservation) => ReservationDto::fromDomain(
                $reservation,
                $customers[$reservation->getCustomerUserId()] ?? null,
            ),
            $reservations,
        );
    }
}
