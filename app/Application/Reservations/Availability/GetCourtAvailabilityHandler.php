<?php

namespace App\Application\Reservations\Availability;

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
    ) {}

    public function handle(GetCourtAvailabilityQuery $query): CourtAvailabilityDto
    {
        $court = $this->courts->findById($query->courtId);

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
            $next = $current->add(new DateInterval("PT{$intervalMinutes}M"));

            /*
             * Si el último slot se pasa del horario
             * de cierre, no lo mostramos.
             */
            if ($next > $closing) {
                break;
            }

            $available = true;

            foreach ($blockingReservations as $reservation) {
                /*
                 * Misma regla de overlap:
                 *
                 * existing.start < slot.end
                 * &&
                 * existing.end > slot.start
                 */
                if ($reservation->getStartsAt() < $next && $reservation->getEndsAt() > $current) {
                    $available = false;
                    break;
                }
            }

            $slots[] = new AvailabilitySlotDto(
                startsAt: $current->format('Y-m-d H:i:s'),
                endsAt: $next->format('Y-m-d H:i:s'),
                available: $available,
            );

            $current = $next;
        }

        return new CourtAvailabilityDto(
            courtId: $court->getId(),
            date: $day,
            intervalMinutes: $intervalMinutes,
            slots: $slots,
        );
    }
}
