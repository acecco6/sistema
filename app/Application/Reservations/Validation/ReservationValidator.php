<?php

namespace App\Application\Reservations\Validation;

use App\Application\Reservations\Support\BranchOperatingWindow;
use App\Domain\Branches\Entities\Branch;
use App\Domain\Courts\Entities\Court;
use App\Domain\Courts\Repositories\IntervalTimeTipoCourtRepository;
use App\Domain\Reservations\Exceptions\CourtNotAvailableException;
use App\Domain\Reservations\Exceptions\InvalidReservationDurationException;
use App\Domain\Reservations\Exceptions\InvalidReservationTimeException;
use App\Domain\Reservations\Exceptions\ReservationOutsideBranchHoursException;
use App\Domain\Reservations\Repositories\ReservationRepository;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

final class ReservationValidator
{
    public function __construct(
        private ReservationRepository $reservations,
        private IntervalTimeTipoCourtRepository $intervals,
    ) {}

    public function validate(
        Court $court,
        Branch $branch,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
    ): void {
        $this->validateTimeRange($startsAt, $endsAt);
        $this->validateBranchHours($branch, $startsAt, $endsAt);
        $this->validateDuration($court, $branch, $startsAt, $endsAt);
        $this->validateAvailability($court, $startsAt, $endsAt);
    }

    private function validateTimeRange(
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
    ): void {
        if ($endsAt <= $startsAt) {
            throw new InvalidReservationTimeException(
                'La fecha de finalización debe ser posterior a la fecha de inicio.'
            );
        }

        if ($startsAt <= CarbonImmutable::now()) {
            throw new InvalidReservationTimeException(
                'No se puede crear una reserva en el pasado.'
            );
        }
    }

    private function validateBranchHours(
        Branch $branch,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
    ): void {
        /*
         * Buscamos la jornada operativa a la que pertenece el inicio.
         *
         * Ejemplo:
         * sucursal 08:00 -> 02:00
         * reserva   23:30 -> 00:30
         * ventana   08:00 -> 02:00 del día siguiente
         */
        $window = BranchOperatingWindow::containing(
            $branch,
            $startsAt,
        );

        if (! $window->containsRange($startsAt, $endsAt)) {
            throw new ReservationOutsideBranchHoursException();
        }
    }

    private function validateDuration(
        Court $court,
        Branch $branch,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
    ): void {
        $intervalMinutes = $this->intervals->findIntervalMinutes(
            branchId: $branch->getId(),
            tipoCourtId: $court->getTipoCourtId(),
        );

        if ($intervalMinutes === null) {
            throw new InvalidReservationDurationException();
        }

        $this->validateStartAlignedWithInterval(
            branch: $branch,
            startsAt: $startsAt,
            intervalMinutes: $intervalMinutes,
        );

        $durationMinutes = (int) (
            ($endsAt->getTimestamp() - $startsAt->getTimestamp()) / 60
        );

        if ($durationMinutes < 60) {
            throw new InvalidReservationDurationException(
                'La duración mínima de una reserva es de 60 minutos.'
            );
        }

        if ($durationMinutes % $intervalMinutes !== 0) {
            throw new InvalidReservationDurationException(
                "La duración debe ser múltiplo de {$intervalMinutes} minutos."
            );
        }
    }

    private function validateAvailability(
        Court $court,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
    ): void {
        $hasOverlap = $this->reservations->hasOverlap(
            courtId: $court->getId(),
            startsAt: $startsAt,
            endsAt: $endsAt,
        );

        if ($hasOverlap) {
            throw new CourtNotAvailableException();
        }
    }

    private function validateStartAlignedWithInterval(
        Branch $branch,
        DateTimeImmutable $startsAt,
        int $intervalMinutes,
    ): void {
        $window = BranchOperatingWindow::containing(
            $branch,
            $startsAt,
        );

        if ($startsAt < $window->opening || $startsAt >= $window->closing) {
            throw new InvalidReservationTimeException(
                'La hora de inicio no corresponde a un turno válido.'
            );
        }

        $minutesFromOpening = (int) (
            ($startsAt->getTimestamp() - $window->opening->getTimestamp()) / 60
        );

        if ($minutesFromOpening % $intervalMinutes !== 0) {
            throw new InvalidReservationTimeException(
                'La hora de inicio no corresponde a un turno válido.'
            );
        }
    }
}
