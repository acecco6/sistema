<?php

namespace App\Application\Reservations\FixedReservations\Get;

use App\Application\Reservations\FixedReservations\DTOs\FixedReservationDto;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationRepository;

final class GetFixedReservationsHandler
{
    public function __construct(
        private FixedReservationRepository $fixedReservations,
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

                return FixedReservationDto::fromDomain(
                    fixedReservation: $fixedReservation,
                    slots: $slots,
                );
            },
            $series
        );
    }
}
