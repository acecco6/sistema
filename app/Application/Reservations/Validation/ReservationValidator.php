<?php

namespace App\Application\Reservations\Validation;

use App\Domain\Branches\Entities\Branch;
use App\Domain\Courts\Entities\Court;
use App\Domain\Courts\Repositories\IntervalTimeTipoCourtRepository;
use App\Domain\Reservations\Exceptions\CourtNotAvailableException;
use App\Domain\Reservations\Exceptions\InvalidReservationDurationException;
use App\Domain\Reservations\Exceptions\InvalidReservationTimeException;
use App\Domain\Reservations\Exceptions\ReservationOutsideBranchHoursException;
use App\Domain\Reservations\Repositories\ReservationRepository;
use DateTimeImmutable;

final class ReservationValidator
{
    public function __construct(
        private ReservationRepository $reservations,
        private IntervalTimeTipoCourtRepository $intervals,
    ) {}

    public function validate(Court $court, Branch $branch, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt,): void
    {
        $this->validateTimeRange(startsAt: $startsAt, endsAt: $endsAt,);

        $this->validateBranchHours(branch: $branch, startsAt: $startsAt, endsAt: $endsAt,);

        $this->validateDuration(court: $court, branch: $branch, startsAt: $startsAt, endsAt: $endsAt,);

        $this->validateAvailability(court: $court, startsAt: $startsAt, endsAt: $endsAt,);
    }

    private function validateTimeRange(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt,): void
    {
        if ($endsAt <= $startsAt) {
            throw new InvalidReservationTimeException('La fecha de finalización debe ser posterior a la fecha de inicio.');
        }

        /*
         * Por ahora exigimos que la reserva sea futura.
         *
         * Más adelante podemos decidir si personal administrativo
         * tiene excepciones para cargar reservas históricas.
         */
        if ($startsAt <= new DateTimeImmutable()) {
            throw new InvalidReservationTimeException('No se puede crear una reserva en el pasado.');
        }
    }

    private function validateBranchHours(Branch $branch, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt,): void
    {
        /*
         * Ajustá estos getters a los nombres reales de tu Branch.
         *
         * Asumo:
         *
         * getOpeningTime()
         * getClosingTime()
         *
         * devolviendo "08:00:00", "23:00:00", etc.
         */

        $openingTime = $branch->getOpeningTime();
        $closingTime = $branch->getClosingTime();

        $reservationStartTime = $startsAt->format('H:i:s');
        $reservationEndTime = $endsAt->format('H:i:s');

        /*
         * Por ahora no permitimos reservas que crucen de día.
         *
         * Ejemplo:
         *
         * 23:00 → 01:00
         *
         * Eso lo podemos soportar más adelante si existen
         * sucursales que trabajan después de medianoche.
         */
        if ($startsAt->format('Y-m-d') !== $endsAt->format('Y-m-d')) {
            throw new ReservationOutsideBranchHoursException();
        }

        if ($reservationStartTime < $openingTime || $reservationEndTime > $closingTime) {
            throw new ReservationOutsideBranchHoursException();
        }
    }

    private function validateDuration(Court $court, Branch $branch, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt,): void
    {
        $intervalMinutes = $this->intervals->findIntervalMinutes(branchId: $branch->getId(), tipoCourtId: $court->getTipoCourtId(),);

        if ($intervalMinutes === null) {
            throw new InvalidReservationDurationException();
        }

        /*
        * Primero validamos que el inicio caiga
        * exactamente en un slot permitido.
        */
        $this->validateStartAlignedWithInterval(
            branch: $branch,
            startsAt: $startsAt,
            intervalMinutes: $intervalMinutes,
        );

        $durationMinutes = (int) (
            (
                $endsAt->getTimestamp()
                - $startsAt->getTimestamp()
            ) / 60
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

    private function validateAvailability(Court $court, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt,): void
    {
        $hasOverlap = $this->reservations->hasOverlap(
            courtId: $court->getId(),
            startsAt: $startsAt,
            endsAt: $endsAt,
        );

        if ($hasOverlap) {
            throw new CourtNotAvailableException();
        }
    }

    private function validateStartAlignedWithInterval(Branch $branch, DateTimeImmutable $startsAt, int $intervalMinutes,): void
    {
        $openingTime = $branch->getOpeningTime();

        /*
     * Construimos la fecha/hora de apertura
     * para el mismo día de la reserva.
     */
        $openingDateTime = new DateTimeImmutable(
            $startsAt->format('Y-m-d')
                . ' '
                . $openingTime
        );

        /*
     * Si intenta empezar antes de la apertura,
     * ya es inválido.
     */
        if ($startsAt < $openingDateTime) {
            throw new InvalidReservationTimeException('La hora de inicio no corresponde a un turno válido.');
        }

        /*
     * Calculamos cuántos minutos pasaron desde
     * la apertura de la sucursal.
     *
     * Ejemplo:
     *
     * apertura = 08:00
     * reserva  = 09:30
     *
     * diferencia = 90 minutos
     */
        $minutesFromOpening = (int) (
            (
                $startsAt->getTimestamp()
                - $openingDateTime->getTimestamp()
            ) / 60
        );

        /*
     * Si la diferencia no es múltiplo del intervalo,
     * el horario no está alineado con un slot.
     *
     * interval = 30
     *
     * 90 % 30 = 0  ✅
     * 75 % 30 = 15 ❌
     */
        if ($minutesFromOpening % $intervalMinutes !== 0) {
            throw new InvalidReservationTimeException('La hora de inicio no corresponde a un turno válido.');
        }
    }
}
