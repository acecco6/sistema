<?php

namespace App\Jobs;

use App\Application\Reservations\FixedReservations\Services\GenerateFixedReservationOccurrences;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationRepository;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class GenerateFixedReservationOccurrencesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(
        FixedReservationRepository $fixedReservations,
        GenerateFixedReservationOccurrences $generator,
    ): void {
        $from = new DateTimeImmutable('today');

        /*
         * Ventana de exactamente 8 semanas.
         *
         * Restamos un día porque GenerateFixedReservationOccurrences
         * trabaja con rango inclusivo.
         */
        $to = $from->add(new DateInterval('P8W'))->sub(new DateInterval('P1D'));

        $series = $fixedReservations->findActiveBetween(from: $from, to: $to);

        foreach ($series as $fixedReservation) {
            try {
                $generator->generate(
                    fixedReservation: $fixedReservation,
                    from: $from,
                    to: $to,
                    skipConflicts: true,
                );
            } catch (Throwable $exception) {
                /*
                 * IMPORTANTE:
                 *
                 * Una serie problemática no debe impedir
                 * generar las demás.
                 *
                 * Ejemplo:
                 * aparece una reserva manual que entra en
                 * conflicto con una ocurrencia futura.
                 */
                report($exception);
            }
        }
    }
}
