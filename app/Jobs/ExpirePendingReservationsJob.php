<?php

namespace App\Jobs;

use App\Domain\Reservations\Events\ReservationExpired;
use App\Domain\Reservations\Repositories\ReservationRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpirePendingReservationsJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        ReservationRepository $reservations
    ): void {
        $expiredReservations = $reservations
            ->findExpiredPending();

        foreach ($expiredReservations as $reservation) {
            $reservation->expire();

            $updated = $reservations->update($reservation);

            ReservationExpired::dispatch(
                $updated->getId()
            );
        }
    }
}
