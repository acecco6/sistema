<?php

namespace App\Application\Reservations\Customer;

use App\Application\Reservations\DTOs\ReservationDto;
use App\Application\Reservations\DTOs\ReservationDtoFactory;
use App\Application\Customers\Contracts\CustomerRepository;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class ShowCustomerReservationHandler
{
    public function __construct(
        private ReservationRepository $reservations,
        private ReservationDtoFactory $dtoFactory,
        private CustomerRepository $customers,
    ) {}

    public function handle(ShowCustomerReservationQuery $query): ReservationDto
    {

        $reservation = $this->reservations->findById($query->reservationId);

        if ($reservation === null || ! $this->belongsToUser($reservation, $query->customerUserId)) {
            throw new ReservationNotFoundException();
        }

        return $this->dtoFactory->create($reservation);
    }

    private function belongsToUser($reservation, int $userId): bool
    {
        return $reservation->getCustomerUserId() === $userId
            || ($reservation->getClubCustomerId() !== null && $this->customers->belongsToUser($reservation->getClubCustomerId(), $userId));
    }
}
