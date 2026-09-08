<?php

namespace App\Application\Reservations\FixedReservations\Deactivate;

use App\Application\Reservations\FixedReservations\DTOs\FixedReservationDto;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\FixedReservations\Exceptions\FixedReservationNotFoundException;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationRepository;
use App\Domain\Reservations\Repositories\ReservationRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class DeactivateFixedReservationHandler
{
    public function __construct(
        private FixedReservationRepository $fixedReservations,
        private ReservationRepository $reservations,
    ) {}

    public function handle(
        DeactivateFixedReservationCommand $command
    ): FixedReservationDto {
        return DB::transaction(
            function () use ($command) {
                $fixedReservation =
                    $this->fixedReservations->findById(
                        $command->id
                    );

                if ($fixedReservation === null) {
                    throw new FixedReservationNotFoundException();
                }

                $fixedReservation->deactivate();

                $fixedReservation =
                    $this->fixedReservations->update(
                        $fixedReservation
                    );

                $futureReservations =
                    $this->reservations
                    ->findFutureByFixedReservation(
                        fixedReservationId: $fixedReservation->getId(),

                        from: new DateTimeImmutable(),
                    );

                foreach ($futureReservations as $reservation) {
                    /*
                     * No tocamos reservas que ya estén
                     * terminadas o canceladas.
                     */
                    if (
                        in_array(
                            $reservation->getStatus(),
                            [
                                ReservationStatus::CANCELLED,
                                ReservationStatus::COMPLETED,
                                ReservationStatus::EXPIRED,
                            ],
                            true
                        )
                    ) {
                        continue;
                    }

                    $reservation->cancel(
                        new DateTimeImmutable()
                    );

                    $this->reservations->update(
                        $reservation
                    );
                }

                $slots = $this->fixedReservations
                    ->findSlotsByFixedReservationId(
                        $fixedReservation->getId()
                    );

                return FixedReservationDto::fromDomain(
                    fixedReservation: $fixedReservation,

                    slots: $slots,
                );
            },
            attempts: 3,
        );
    }
}
