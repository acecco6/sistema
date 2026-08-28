<?php

namespace App\Application\Reservations\Availability;

use App\Application\Pricing\Resolver\PriceResolver;
use App\Application\Reservations\DTOs\AvailabilitySlotDto;
use App\Application\Reservations\DTOs\CourtAvailabilityDto;
use App\Domain\Branches\Exceptions\BranchInactiveException;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Courts\Exceptions\CourtInactiveException;
use App\Domain\Courts\Exceptions\CourtNotFoundException;
use App\Domain\Courts\Repositories\CourtRepository;
use App\Domain\Courts\Repositories\IntervalTimeTipoCourtRepository;
use App\Domain\Reservations\Exceptions\InvalidReservationDurationException;
use App\Domain\Reservations\Repositories\ReservationRepository;
use DateInterval;
use DateTimeImmutable;

final class GetCourtAvailabilityHandler
{
    public function __construct(
        private CourtRepository $courts,
        private BranchRepository $branches,
        private IntervalTimeTipoCourtRepository $intervals,
        private ReservationRepository $reservations,
        private PriceResolver $priceResolver,
    ) {}

    public function handle(GetCourtAvailabilityQuery $query): CourtAvailabilityDto
    {
        $court = $this->courts->findById($query->courtId);
        $durationMinutes = $query->durationMinutes;
        if ($durationMinutes < 60) {
            throw new InvalidReservationDurationException('La duración mínima de una reserva es de 60 minutos.');
        }

        if ($court === null) {
            throw new CourtNotFoundException();
        }

        if (! $court->isActive()) {
            throw new CourtInactiveException();
        }

        $branch = $this->branches->findById($court->getBranchId());

        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        if (! $branch->isActive()) {
            throw new BranchInactiveException();
        }

        $intervalMinutes = $this->intervals->findIntervalMinutes($branch->getId(), $court->getTipoCourtId());

        if ($intervalMinutes === null) {
            throw new InvalidReservationDurationException();
        }

        if ($durationMinutes % $intervalMinutes !== 0) {
            throw new InvalidReservationDurationException(
                "La duración debe ser múltiplo de {$intervalMinutes} minutos."
            );
        }

        /*
         * Inicio y fin operativo del día consultado.
         */
        $day = $query->date->format('Y-m-d');

        $opening = new DateTimeImmutable($day . ' ' . $branch->getOpeningTime());
        $closing = new DateTimeImmutable($day . ' ' . $branch->getClosingTime());

        /*
         * Traemos todas las reservas que bloquean
         * algún tramo de ese día.
         */
        $blockingReservations = $this->reservations->findBlockingReservationsBetween($court->getId(), $opening, $closing);

        $slots = [];

        $current = $opening;

        while ($current < $closing) {

            /*
            * El final del slot depende de cuánto
            * quiere jugar el usuario.
            */
            $slotEnd = $current->add(
                new DateInterval(
                    "PT{$durationMinutes}M"
                )
            );

            /*
            * Si la reserva terminaría después
            * del cierre, no mostramos ese horario.
            */
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

            if ($available) {
                $price = $this->priceResolver->resolve(
                    branchId: $branch->getId(),
                    tipoCourtId: $court->getTipoCourtId(),
                    startsAt: $current,
                    endsAt: $slotEnd,
                )->total;

                $slots[] = new AvailabilitySlotDto(
                    startsAt: $current->format(
                        'Y-m-d H:i:s'
                    ),
                    endsAt: $slotEnd->format(
                        'Y-m-d H:i:s'
                    ),
                    available: $available,
                    totalPrice: $price,
                );
            }

            /*
            * IMPORTANTE:
            *
            * Los posibles horarios de inicio
            * siguen avanzando según intervalMinutes.
            */
            $current = $current->add(new DateInterval("PT{$intervalMinutes}M"));
        }

        return new CourtAvailabilityDto(
            courtId: $court->getId(),
            date: $day,
            intervalMinutes: $intervalMinutes,
            durationMinutes: $durationMinutes,
            slots: $slots,
        );
    }
}
