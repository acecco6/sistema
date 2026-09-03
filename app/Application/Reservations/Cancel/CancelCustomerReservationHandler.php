<?php

namespace App\Application\Reservations\Cancel;

use App\Application\Reservations\DTOs\ReservationDto;
use App\Domain\Reservations\Events\ReservationCancelled;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class CancelCustomerReservationHandler
{
    public function __construct(
        private ReservationRepository $reservations,
    ) {}

    public function handle(CancelCustomerReservationCommand $command): ReservationDto
    {

        $reservation = $this->reservations->findById($command->reservationId);

        /*
         * No revelamos si la reserva existe pero
         * pertenece a otra persona.
         *
         * Para el cliente es simplemente "no encontrada".
         */
        if ($reservation === null || $reservation->getCustomerUserId() !== $command->customerUserId) {
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
