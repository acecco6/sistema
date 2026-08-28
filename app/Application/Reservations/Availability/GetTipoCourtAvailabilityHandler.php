<?php

namespace App\Application\Reservations\Availability;

use App\Application\Reservations\Availability\GetTipoCourtAvailabilityQuery;
use App\Application\Reservations\DTOs\AvailabilitySlotDto;
use App\Application\Reservations\DTOs\CourtAvailabilitySummaryDto;
use App\Application\Reservations\DTOs\TipoCourtAvailabilityDto;
use App\Domain\Branches\Exceptions\BranchInactiveException;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Courts\Repositories\CourtRepository;
use App\Domain\Courts\Repositories\IntervalTimeTipoCourtRepository;
use App\Domain\Reservations\Exceptions\InvalidReservationDurationException;
use App\Domain\Reservations\Repositories\ReservationRepository;
use DateInterval;
use DateTimeImmutable;

final class GetTipoCourtAvailabilityHandler
{
    public function __construct(
        private BranchRepository $branches,
        private CourtRepository $courts,
        private IntervalTimeTipoCourtRepository $intervals,
        private ReservationRepository $reservations,
    ) {}

    public function handle(GetTipoCourtAvailabilityQuery $query): TipoCourtAvailabilityDto
    {
        $branch = $this->branches->findById(
            $query->branchId
        );

        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        if (! $branch->isActive()) {
            throw new BranchInactiveException();
        }

        $intervalMinutes = $this->intervals->findIntervalMinutes(
            branchId: $query->branchId,
            tipoCourtId: $query->tipoCourtId,
        );

        if ($intervalMinutes === null) {
            throw new InvalidReservationDurationException();
        }

        $durationMinutes = $query->durationMinutes;

        if ($durationMinutes < 60) {
            throw new InvalidReservationDurationException('La duración mínima de una reserva es de 60 minutos.');
        }

        if ($durationMinutes % $intervalMinutes !== 0) {
            throw new InvalidReservationDurationException("La duración debe ser múltiplo de {$intervalMinutes} minutos.");
        }

        $courts = $this->courts
            ->findActiveByBranchAndTipo(
                branchId: $query->branchId,
                tipoCourtId: $query->tipoCourtId,
            );

        $day = $query->date->format('Y-m-d');

        $opening = new DateTimeImmutable(
            $day . ' ' . $branch->getOpeningTime()
        );

        $closing = new DateTimeImmutable(
            $day . ' ' . $branch->getClosingTime()
        );

        /*
         * Si llegan startTime/endTime, limitamos la búsqueda.
         *
         * Ejemplo:
         * ?start_time=18:00:00&end_time=20:00:00
         */
        if (
            $query->startTime !== null
            && $query->endTime !== null
        ) {
            $requestedStart = new DateTimeImmutable(
                $day . ' ' . $query->startTime
            );

            $requestedEnd = new DateTimeImmutable(
                $day . ' ' . $query->endTime
            );

            if (
                $requestedStart >= $opening
                && $requestedStart < $closing
            ) {
                $opening = $requestedStart;
            }

            if (
                $requestedEnd > $opening
                && $requestedEnd <= $closing
            ) {
                $closing = $requestedEnd;
            }
        }

        $courtResults = [];

        foreach ($courts as $court) {
            $blockingReservations =
                $this->reservations
                ->findBlockingReservationsBetween(
                    courtId: $court->getId(),
                    startsAt: $opening,
                    endsAt: $closing,
                );

            $slots = [];

            $current = $opening;

            while ($current < $closing) {

                $slotEnd = $current->add(
                    new DateInterval(
                        "PT{$durationMinutes}M"
                    )
                );

                if ($slotEnd > $closing) {
                    break;
                }

                $available = true;

                foreach ($blockingReservations as $reservation) {
                    if (
                        $reservation->getStartsAt() < $slotEnd
                        &&
                        $reservation->getEndsAt() > $current
                    ) {
                        $available = false;

                        break;
                    }
                }

                $slots[] = new AvailabilitySlotDto(
                    startsAt: $current->format(
                        'Y-m-d H:i:s'
                    ),
                    endsAt: $slotEnd->format(
                        'Y-m-d H:i:s'
                    ),
                    available: $available,
                );

                $current = $current->add(
                    new DateInterval(
                        "PT{$intervalMinutes}M"
                    )
                );
            }

            $courtResults[] =
                new CourtAvailabilitySummaryDto(
                    courtId: $court->getId(),
                    courtName: $court->getName(),
                    slots: $slots,
                );
        }

        return new TipoCourtAvailabilityDto(
            branchId: $query->branchId,
            tipoCourtId: $query->tipoCourtId,
            date: $day,
            intervalMinutes: $intervalMinutes,
            durationMinutes: $durationMinutes,
            courts: $courtResults,
        );
    }
}
