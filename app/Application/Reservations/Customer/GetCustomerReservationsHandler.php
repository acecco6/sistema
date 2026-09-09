<?php

namespace App\Application\Reservations\Customer;

use App\Application\Reservations\DTOs\ReservationDto;
use App\Application\Reservations\DTOs\ReservationDtoFactory;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class GetCustomerReservationsHandler
{
    public function __construct(
        private ReservationRepository $reservations,
        private ReservationDtoFactory $dtoFactory,
    ) {}

    /**
     * @return ReservationDto[]
     */
    public function handle(GetCustomerReservationsQuery $query): array
    {
        $reservations = $this->reservations->findByCustomerUser($query->customerUserId);

        return $this->dtoFactory->createMany($reservations);
    }
}
