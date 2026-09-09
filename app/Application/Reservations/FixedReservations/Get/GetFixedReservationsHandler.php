<?php

namespace App\Application\Reservations\FixedReservations\Get;

use App\Application\Reservations\FixedReservations\DTOs\FixedReservationDto;
use App\Application\Reservations\FixedReservations\DTOs\FixedReservationDtoFactory;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationRepository;

final class GetFixedReservationsHandler
{
    public function __construct(
        private FixedReservationRepository $fixedReservations,
        private FixedReservationDtoFactory $dtoFactory,
    ) {}

    public function handle(
        GetFixedReservationsQuery $query
    ): array {
        $series = $this->fixedReservations
            ->findByClubId(
                $query->clubId
            );

        return array_map(
            function ($fixedReservation) {
                $slots = $this->fixedReservations
                    ->findSlotsByFixedReservationId(
                        $fixedReservation->getId()
                    );

                return $this->dtoFactory->create($fixedReservation, $slots);
            },
            $series
        );
    }
}
