<?php

namespace App\Application\Reservations\Guest;

use App\Application\Reservations\DTOs\ReservationDto;
use App\Domain\Reservations\Events\ReservationCancelled;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class CancelGuestReservationHandler
{
    public function __construct(
        private ReservationRepository $reservations,
    ) {}

    public function handle(CancelGuestReservationCommand $command): ReservationDto
    {

        $reservation = $this->reservations->findByPublicToken($command->publicToken);

        if ($reservation === null || $reservation->getCustomerUserId() !== null) {
            throw new ReservationNotFoundException();
        }

        $reservation->cancelByCustomer($command->cancelledAt ?? now()->toDateTimeImmutable());

        $updated = $this->reservations->update($reservation);

        ReservationCancelled::dispatch(
            $updated->getId()
        );

        return ReservationDto::fromDomain($updated);
    }
}
