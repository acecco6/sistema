<?php

namespace App\Application\Reservations\FixedReservations\Services;

use App\Application\Reservations\Create\CreateReservationCommand;
use App\Application\Reservations\Create\CreateReservationHandler;
use App\Domain\Reservations\FixedReservations\Entities\FixedReservation;
use App\Domain\Reservations\FixedReservations\Entities\FixedReservationSlot;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationRepository;
use App\Domain\Reservations\Repositories\ReservationRepository;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;

final class GenerateFixedReservationOccurrences
{
    public function __construct(
        private FixedReservationRepository $fixedReservations,
        private ReservationRepository $reservations,
        private CreateReservationHandler $createReservationHandler,
    ) {}

    public function generate(
        FixedReservation $fixedReservation,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): void {
        if (! $fixedReservation->isActive()) {
            return;
        }

        $from = $this->maxDate(
            $from,
            $fixedReservation->getStartsOn()
        );

        if ($fixedReservation->getEndsOn() !== null) {
            $to = $this->minDate(
                $to,
                $fixedReservation->getEndsOn()
            );
        }

        if ($from > $to) {
            return;
        }

        $slots = $this->fixedReservations
            ->findActiveSlotsByFixedReservationId(
                $fixedReservation->getId()
            );

        foreach ($slots as $slot) {
            $this->generateSlot(
                fixedReservation: $fixedReservation,
                slot: $slot,
                from: $from,
                to: $to,
            );
        }
    }

    private function generateSlot(
        FixedReservation $fixedReservation,
        FixedReservationSlot $slot,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): void {
        foreach (
            $this->datesBetween($from, $to)
            as $date
        ) {
            if (
                (int) $date->format('N')
                !== $slot->getDayOfWeek()
            ) {
                continue;
            }

            if (
                $this->reservations->existsFixedOccurrence(
                    fixedReservationSlotId: $slot->getId(),
                    recurrenceDate: $date,
                )
            ) {
                continue;
            }

            $startsAt = $this->buildStartsAt(
                date: $date,
                startTime: $slot->getStartTime(),
            );

            $endsAt = $startsAt->add(
                new DateInterval(
                    sprintf(
                        'PT%dM',
                        $slot->getDurationMinutes()
                    )
                )
            );

            /*
             * Puede ocurrir que el primer día de la ventana
             * sea hoy y el horario ya haya pasado.
             */
            if ($startsAt <= new DateTimeImmutable()) {
                continue;
            }

            $this->createReservationHandler->handle(
                new CreateReservationCommand(
                    courtId: $slot->getCourtId(),

                    customerUserId: $fixedReservation
                        ->getCustomerUserId(),

                    createdByUserId: $fixedReservation
                        ->getCreatedByUserId(),

                    guestName: $fixedReservation
                        ->getGuestName(),

                    guestEmail: $fixedReservation
                        ->getGuestEmail(),

                    guestPhone: $fixedReservation
                        ->getGuestPhone(),

                    startsAt: $startsAt,

                    endsAt: $endsAt,

                    notes: $fixedReservation->getNotes(),

                    /*
                     * Las reservas fijas no pueden ser
                     * PENDING porque expirarían a los 15 min.
                     */
                    confirmed: true,

                    fixedReservationSlotId: $slot->getId(),

                    recurrenceDate: $date,
                )
            );
        }
    }

    /**
     * @return iterable<DateTimeImmutable>
     */
    private function datesBetween(
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): iterable {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);

        $period = new DatePeriod(
            $from,
            new DateInterval('P1D'),
            $to->add(
                new DateInterval('P1D')
            )
        );

        foreach ($period as $date) {
            yield DateTimeImmutable::createFromInterface(
                $date
            );
        }
    }

    private function buildStartsAt(
        DateTimeImmutable $date,
        string $startTime
    ): DateTimeImmutable {
        return new DateTimeImmutable(
            sprintf(
                '%s %s',
                $date->format('Y-m-d'),
                $startTime,
            )
        );
    }

    private function maxDate(
        DateTimeImmutable $a,
        DateTimeImmutable $b
    ): DateTimeImmutable {
        return $a >= $b
            ? $a
            : $b;
    }

    private function minDate(
        DateTimeImmutable $a,
        DateTimeImmutable $b
    ): DateTimeImmutable {
        return $a <= $b
            ? $a
            : $b;
    }
}
