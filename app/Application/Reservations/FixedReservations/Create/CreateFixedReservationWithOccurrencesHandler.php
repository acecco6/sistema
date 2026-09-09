<?php

namespace App\Application\Reservations\FixedReservations\Create;

use App\Application\Reservations\FixedReservations\DTOs\FixedReservationDto;
use App\Application\Reservations\FixedReservations\Services\GenerateFixedReservationOccurrences;
use App\Application\Reservations\FixedReservations\Validation\FixedReservationScheduleValidator;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationRepository;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class CreateFixedReservationWithOccurrencesHandler
{
    public function __construct(
        private CreateFixedReservationHandler $createHandler,
        private FixedReservationRepository $fixedReservations,
        private GenerateFixedReservationOccurrences $generator,
        private FixedReservationScheduleValidator $scheduleValidator,
    ) {}

    public function handle(
        CreateFixedReservationCommand $command
    ): FixedReservationDto {
        $from = new DateTimeImmutable('today');

        $to = $from->add(
            new DateInterval('P8W')
        );

        /*
         * 1. Validamos toda la agenda futura
         * antes de crear la serie.
         */
        $result = DB::transaction(function () use ($command, $from, $to) {
            $this->scheduleValidator->validate(command: $command, from: $from, to: $to);
            return $this->createHandler->handle($command);
        }, attempts: 3);

        /*
         * 3. Recuperamos la serie.
         */
        $fixedReservation =
            $this->fixedReservations->findById(
                $result->id
            );

        if ($fixedReservation === null) {
            return $result;
        }

        /*
         * 4. Materializamos las próximas 8 semanas.
         *
         * CreateReservationHandler vuelve a validar
         * y toma lock sobre cada Court.
         */
        $this->generator->generate(
            fixedReservation: $fixedReservation,
            from: $from,
            to: $to,
        );

        return $result;
    }
}
