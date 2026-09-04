<?php

namespace App\Application\Reservations\Collection;

use App\Application\Reservations\DTOs\ReservationDto;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class GetCourtReservationsHandler
{
    public function __construct(
        private ReservationRepository $reservations,
    ) {}

    public function handle(GetCourtReservationsQuery $query): array
    {

        $reservations = $this->reservations->findByCourtAndDate($query->courtId, $query->date);

        return array_map(ReservationDto::fromDomain(...), $reservations);
    }
}
