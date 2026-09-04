<?php

namespace App\Jobs;

use App\Domain\Reservations\Events\ReservationCompleted;
use App\Domain\Reservations\Repositories\ReservationRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CompleteFinishedReservationsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ReservationRepository $reservations): void
    {
        $finishedReservations = $reservations->findFinishedConfirmed();

        foreach ($finishedReservations as $reservation) {

            /*
             * La propia entidad protege la transición:
             *
             * solamente CONFIRMED puede pasar
             * a COMPLETED.
             */
            $reservation->complete();

            $updated = $reservations->update(
                $reservation
            );

            ReservationCompleted::dispatch(
                $updated->getId()
            );
        }
    }
}
