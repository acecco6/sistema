<?php

namespace App\Application\Reservations\Availability;

use App\Application\Pricing\Resolver\PriceResolver;
use App\Application\Reservations\DTOs\AvailabilitySlotDto;
use App\Application\Reservations\DTOs\CourtAvailabilitySummaryDto;
use App\Application\Reservations\DTOs\TipoCourtAvailabilityDto;
use App\Application\Reservations\Support\BranchOperatingWindow;
use App\Domain\Branches\Exceptions\BranchInactiveException;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Courts\Repositories\CourtRepository;
use App\Domain\Courts\Repositories\IntervalTimeTipoCourtRepository;
use App\Domain\Reservations\Exceptions\InvalidReservationDurationException;
use App\Domain\Reservations\Exceptions\InvalidReservationTimeException;
use App\Domain\Reservations\Repositories\ReservationRepository;
use Carbon\CarbonImmutable;
use DateInterval;

final class GetTipoCourtAvailabilityHandler
{
    public function __construct(
        private BranchRepository $branches,
        private CourtRepository $courts,
        private IntervalTimeTipoCourtRepository $intervals,
        private ReservationRepository $reservations,
        private PriceResolver $priceResolver,
    ) {}

    public function handle(GetTipoCourtAvailabilityQuery $query): TipoCourtAvailabilityDto
    {
        $branch = $this->branches->findById($query->branchId);

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
            throw new InvalidReservationDurationException(
                'La duración mínima de una reserva es de 60 minutos.'
            );
        }

        if ($durationMinutes % $intervalMinutes !== 0) {
            throw new InvalidReservationDurationException(
                "La duración debe ser múltiplo de {$intervalMinutes} minutos."
            );
        }

        $courts = $this->courts->findActiveByBranchAndTipo(
            branchId: $query->branchId,
            tipoCourtId: $query->tipoCourtId,
        );

        $day = $query->date->format('Y-m-d');

        $window = BranchOperatingWindow::forBusinessDate(
            $branch,
            $query->date,
        );

        $opening = $window->opening;
        $closing = $window->closing;
        $minimumStart = $opening;

        /*
         * start_time y end_time son filtros de la ventana operativa.
         * Nunca usamos start_time como origen de la grilla.
         *
         * En una jornada 08:00 -> 02:00:
         *   23:00 pertenece al día consultado.
         *   01:00 pertenece al día siguiente.
         */
        if ($query->startTime !== null && $query->endTime !== null) {
            $requestedStart = $window->dateTimeForClock($query->startTime);
            $requestedEnd = $window->dateTimeForClock($query->endTime);

            if ($requestedEnd <= $requestedStart) {
                throw new InvalidReservationTimeException(
                    'El rango horario solicitado no es válido.'
                );
            }

            if ($requestedStart > $minimumStart) {
                $minimumStart = $requestedStart;
            }

            if ($requestedEnd < $closing) {
                $closing = $requestedEnd;
            }
        }

        /*
         * Nunca ofrecemos slots anteriores a ahora.
         * Esto también funciona después de medianoche para la jornada
         * iniciada el día anterior, porque opening/closing son DateTime reales.
         */
        $now = CarbonImmutable::now();

        if ($now >= $closing) {
            $minimumStart = $closing;
        } elseif ($now > $minimumStart) {
            $minimumStart = $now;
        }

        $opening = $window->alignToNextSlot(
            minimumStart: $minimumStart,
            intervalMinutes: $intervalMinutes,
        );

        if ($opening > $closing) {
            $opening = $closing;
        }

        $courtResults = [];

        foreach ($courts as $court) {
            $blockingReservations = $this->reservations
                ->findBlockingReservationsBetween(
                    courtId: $court->getId(),
                    startsAt: $opening,
                    endsAt: $closing,
                );

            $slots = [];
            $current = $opening;

            while ($current < $closing) {
                $slotEnd = $current->add(
                    new DateInterval("PT{$durationMinutes}M")
                );

                if ($slotEnd > $closing) {
                    break;
                }

                $available = true;

                foreach ($blockingReservations as $reservation) {
                    if (
                        $reservation->getStartsAt() < $slotEnd
                        && $reservation->getEndsAt() > $current
                    ) {
                        $available = false;
                        break;
                    }
                }

                if ($available) {
                    $price = $this->priceResolver->resolve(
                        branchId: $branch->getId(),
                        tipoCourtId: $court->getTipoCourtId(),
                        startsAt: $current,
                        endsAt: $slotEnd,
                    )->total;

                    $slots[] = new AvailabilitySlotDto(
                        startsAt: $current->format('Y-m-d H:i:s'),
                        endsAt: $slotEnd->format('Y-m-d H:i:s'),
                        available: true,
                        totalPrice: $price,
                    );
                }

                $current = $current->add(
                    new DateInterval("PT{$intervalMinutes}M")
                );
            }

            $courtResults[] = new CourtAvailabilitySummaryDto(
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
