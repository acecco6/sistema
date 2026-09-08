<?php

namespace App\Application\Reservations\FixedReservations\Create;

use App\Application\Reservations\FixedReservations\DTOs\FixedReservationDto;
use App\Domain\Branches\Exceptions\BranchInactiveException;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Courts\Exceptions\CourtInactiveException;
use App\Domain\Courts\Exceptions\CourtNotFoundException;
use App\Domain\Courts\Repositories\CourtRepository;
use App\Domain\Reservations\FixedReservations\Entities\FixedReservation;
use App\Domain\Reservations\FixedReservations\Entities\FixedReservationSlot;
use App\Domain\Reservations\FixedReservations\Exceptions\FixedReservationCourtOutsideClubException;
use App\Domain\Reservations\FixedReservations\Exceptions\FixedReservationWithoutSlotsException;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationRepository;
use Illuminate\Support\Facades\DB;

final class CreateFixedReservationHandler
{
    public function __construct(
        private FixedReservationRepository $fixedReservations,
        private CourtRepository $courts,
        private BranchRepository $branches,
    ) {}

    public function handle(
        CreateFixedReservationCommand $command
    ): FixedReservationDto {
        if (empty($command->slots)) {
            throw new FixedReservationWithoutSlotsException();
        }


        if ($command->startsOn->setTime(23, 59, 59) < new \DateTimeImmutable('today')) {
            throw new \InvalidArgumentException('La fecha de inicio de la reserva fija no puede estar en el pasado.');
        }

        /*
         * Validamos todas las canchas ANTES de crear
         * la configuración de recurrencia.
         */

        foreach ($command->slots as $slotData) {
            $this->validateSlot(
                clubId: $command->clubId,
                slotData: $slotData,
            );
        }

        return DB::transaction(
            function () use ($command) {
                $fixedReservation =
                    $this->fixedReservations->save(
                        new FixedReservation(
                            id: null,
                            clubId: $command->clubId,
                            customerUserId: $command->customerUserId,
                            guestName: $command->guestName,
                            guestEmail: $command->guestEmail,
                            guestPhone: $command->guestPhone,
                            createdByUserId: $command->createdByUserId,
                            startsOn: $command->startsOn,
                            endsOn: $command->endsOn,
                            active: true,
                            notes: $command->notes,
                        )
                    );

                $savedSlots = [];

                foreach ($command->slots as $slotData) {
                    $savedSlots[] =
                        $this->fixedReservations->saveSlot(
                            new FixedReservationSlot(
                                id: null,
                                fixedReservationId: $fixedReservation->getId(),
                                courtId: $slotData->courtId,
                                dayOfWeek: $slotData->dayOfWeek,
                                startTime: $slotData->startTime,
                                durationMinutes: $slotData->durationMinutes,
                                active: true,
                            )
                        );
                }

                return FixedReservationDto::fromDomain(
                    fixedReservation: $fixedReservation,
                    slots: $savedSlots,
                );
            },
            attempts: 3,
        );
    }

    private function validateSlot(int $clubId, CreateFixedReservationSlotData $slotData): void
    {
        $court = $this->courts->findById($slotData->courtId);

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

        if ($branch->getClubId() !== $clubId) {
            throw new FixedReservationCourtOutsideClubException();
        }
    }
}
