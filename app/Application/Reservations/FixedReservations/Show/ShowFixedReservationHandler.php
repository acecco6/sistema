<?php

namespace App\Application\Reservations\FixedReservations\Show;

use App\Application\Reservations\FixedReservations\DTOs\FixedReservationDto;
use App\Application\Reservations\FixedReservations\DTOs\FixedReservationDtoFactory;
use App\Domain\Reservations\FixedReservations\Exceptions\FixedReservationNotFoundException;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationRepository;

final class ShowFixedReservationHandler
{
    public function __construct(
        private FixedReservationRepository $fixedReservations,
        private FixedReservationDtoFactory $dtoFactory,
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

        return $this->dtoFactory->create($fixedReservation, $slots);
    }
}
