<?php

namespace App\Application\Reservations\Collection;

use App\Application\Reservations\DTOs\ReservationDto;
use App\Application\Reservations\DTOs\ReservationDtoFactory;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class GetCourtReservationsHandler
{
    public function __construct(
        private ReservationRepository $reservations,
        private ReservationDtoFactory $dtoFactory,
    ) {}

    public function handle(GetCourtReservationsQuery $query): array
    {

        $reservations = $this->reservations->findByCourtAndDate($query->courtId, $query->date);

        return $this->dtoFactory->createMany($reservations);
    }
}
