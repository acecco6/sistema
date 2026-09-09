<?php

namespace App\Application\Reservations\FixedReservations\Validation;

use App\Application\Reservations\FixedReservations\Create\CreateFixedReservationCommand;
use App\Application\Reservations\FixedReservations\Create\CreateFixedReservationSlotData;
use App\Application\Reservations\Validation\ReservationValidator;
use App\Domain\Branches\Exceptions\BranchInactiveException;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Courts\Exceptions\CourtInactiveException;
use App\Domain\Courts\Exceptions\CourtNotFoundException;
use App\Domain\Courts\Repositories\CourtRepository;
use App\Domain\Reservations\Exceptions\CourtNotAvailableException;
use App\Domain\Reservations\FixedReservations\Exceptions\FixedReservationScheduleConflictException;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationRepository;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;

final class FixedReservationScheduleValidator
{
    public function __construct(
        private CourtRepository $courts,
        private BranchRepository $branches,
        private ReservationValidator $reservationValidator,
        private FixedReservationRepository $fixedReservations,
    ) {}

    public function validate(
        CreateFixedReservationCommand $command,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): void {
        $plannedOccurrences = [];
        $requestedSlots = [];

        foreach ($command->slots as $slot) {
            $court = $this->courts->findById(
                $slot->courtId
            );

            if ($court === null) {
                throw new CourtNotFoundException();
            }

            if (! $court->isActive()) {
                throw new CourtInactiveException();
            }

            $branch = $this->branches->findById(
                $court->getBranchId()
            );

            if ($branch === null) {
                throw new BranchNotFoundException();
            }

            if (! $branch->isActive()) {
                throw new BranchInactiveException();
            }

            $this->validateExistingFixedSchedule($command, $slot);
            $this->validateRequestedFixedSchedule($slot, $requestedSlots);
            $requestedSlots[] = $slot;

            foreach (
                $this->occurrenceDates(
                    slot: $slot,
                    startsOn: $command->startsOn,
                    endsOn: $command->endsOn,
                    from: $from,
                    to: $to,
                ) as $date
            ) {
                $startsAt = $this->buildStartsAt(
                    date: $date,
                    startTime: $slot->startTime,
                );

                /*
                 * Si hoy ya pasó ese horario,
                 * no forma parte del horizonte materializable.
                 */
                if ($startsAt <= new DateTimeImmutable()) {
                    continue;
                }

                $endsAt = $startsAt->add(
                    new DateInterval(
                        sprintf(
                            'PT%dM',
                            $slot->durationMinutes
                        )
                    )
                );

                /*
                 * Validamos contra las mismas reglas
                 * que cualquier reserva normal.
                 */
                $this->reservationValidator->validate(
                    court: $court,
                    branch: $branch,
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                );

                /*
                 * También debemos validar contra otros slots
                 * que estamos por crear en esta misma serie.
                 */
                $this->validateInternalOverlap(
                    courtId: $court->getId(),
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                    plannedOccurrences: $plannedOccurrences,
                );

                $plannedOccurrences[] = [
                    'court_id' => $court->getId(),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ];
            }
        }
    }

    /** @param CreateFixedReservationSlotData[] $requestedSlots */
    private function validateRequestedFixedSchedule(
        CreateFixedReservationSlotData $candidate,
        array $requestedSlots,
    ): void {
        foreach ($requestedSlots as $existing) {
            if ($existing->courtId === $candidate->courtId
                && $existing->dayOfWeek === $candidate->dayOfWeek
                && $this->timesOverlap($existing->startTime, $existing->durationMinutes, $candidate->startTime, $candidate->durationMinutes)) {
                throw new FixedReservationScheduleConflictException();
            }
        }
    }

    private function validateExistingFixedSchedule(
        CreateFixedReservationCommand $command,
        CreateFixedReservationSlotData $candidate,
    ): void {
        foreach ($this->fixedReservations->findActiveSchedulesForCourtAndDay($candidate->courtId, $candidate->dayOfWeek) as $existing) {
            if (! $this->rangesHaveOccurrenceOnDay(
                $command->startsOn,
                $command->endsOn,
                $existing['starts_on'],
                $existing['ends_on'],
                $candidate->dayOfWeek,
            )) continue;

            if ($this->timesOverlap($existing['start_time'], $existing['duration_minutes'], $candidate->startTime, $candidate->durationMinutes)) {
                throw new FixedReservationScheduleConflictException();
            }
        }
    }

    private function rangesHaveOccurrenceOnDay(
        DateTimeImmutable $startsA,
        ?DateTimeImmutable $endsA,
        DateTimeImmutable $startsB,
        ?DateTimeImmutable $endsB,
        int $dayOfWeek,
    ): bool {
        $from = $this->maxDate($startsA->setTime(0, 0), $startsB->setTime(0, 0));
        $to = match (true) {
            $endsA === null && $endsB === null => null,
            $endsA === null => $endsB->setTime(0, 0),
            $endsB === null => $endsA->setTime(0, 0),
            default => $this->minDate($endsA->setTime(0, 0), $endsB->setTime(0, 0)),
        };
        if ($to !== null && $from > $to) return false;

        $daysUntil = ($dayOfWeek - (int) $from->format('N') + 7) % 7;
        $firstOccurrence = $from->add(new DateInterval("P{$daysUntil}D"));
        return $to === null || $firstOccurrence <= $to;
    }

    private function timesOverlap(string $startA, int $durationA, string $startB, int $durationB): bool
    {
        $startMinutesA = $this->timeToMinutes($startA);
        $startMinutesB = $this->timeToMinutes($startB);
        return $startMinutesA < $startMinutesB + $durationB && $startMinutesA + $durationA > $startMinutesB;
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));
        return $hours * 60 + $minutes;
    }

    /**
     * @return iterable<DateTimeImmutable>
     */
    private function occurrenceDates(
        CreateFixedReservationSlotData $slot,
        DateTimeImmutable $startsOn,
        ?DateTimeImmutable $endsOn,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): iterable {
        $from = $this->maxDate(
            $from->setTime(0, 0),
            $startsOn->setTime(0, 0)
        );

        if ($endsOn !== null) {
            $to = $this->minDate(
                $to->setTime(0, 0),
                $endsOn->setTime(0, 0)
            );
        } else {
            $to = $to->setTime(0, 0);
        }

        if ($from > $to) {
            return;
        }

        $period = new DatePeriod(
            $from,
            new DateInterval('P1D'),
            $to->add(
                new DateInterval('P1D')
            )
        );

        foreach ($period as $date) {
            $date = DateTimeImmutable::createFromInterface(
                $date
            );

            if (
                (int) $date->format('N')
                !== $slot->dayOfWeek
            ) {
                continue;
            }

            yield $date;
        }
    }

    private function validateInternalOverlap(
        int $courtId,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        array $plannedOccurrences,
    ): void {
        foreach ($plannedOccurrences as $planned) {
            if (
                $planned['court_id'] !== $courtId
            ) {
                continue;
            }

            if (
                $planned['starts_at'] < $endsAt
                && $planned['ends_at'] > $startsAt
            ) {
                throw new CourtNotAvailableException();
            }
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
                $startTime
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
