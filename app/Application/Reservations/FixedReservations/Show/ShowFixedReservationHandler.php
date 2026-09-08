<?php

namespace App\Application\Reservations\FixedReservations\Show;

use App\Application\Reservations\FixedReservations\DTOs\FixedReservationDto;
use App\Domain\Reservations\FixedReservations\Exceptions\FixedReservationNotFoundException;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationRepository;

final class ShowFixedReservationHandler
{
    public function __construct(
        private FixedReservationRepository $fixedReservations,
    ) {}

    public function handle(
        ShowFixedReservationQuery $query
    ): FixedReservationDto {
        $fixedReservation =
            $this->fixedReservations->findById(
                $query->id
            );

        if ($fixedReservation === null) {
            throw new FixedReservationNotFoundException();
        }

        $slots = $this->fixedReservations
            ->findSlotsByFixedReservationId(
                $fixedReservation->getId()
            );

        return FixedReservationDto::fromDomain(
            fixedReservation: $fixedReservation,
            slots: $slots,
        );
    }
}
