<?php

namespace App\Application\Reservations\Customer;

use App\Application\Reservations\DTOs\ReservationDto;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class GetCustomerReservationsHandler
{
    public function __construct(
        private ReservationRepository $reservations,
    ) {}

    /**
     * @return ReservationDto[]
     */
    public function handle(GetCustomerReservationsQuery $query): array
    {
        $reservations = $this->reservations->findByCustomerUser($query->customerUserId);

        return array_map(ReservationDto::fromDomain(...), $reservations);
    }
}
