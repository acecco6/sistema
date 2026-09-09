<?php

namespace App\Application\Reservations\FixedReservations\Create;

use App\Application\Reservations\FixedReservations\DTOs\FixedReservationDto;
use App\Application\Reservations\FixedReservations\DTOs\FixedReservationDtoFactory;
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
use App\Application\Customers\Contracts\CustomerRepository;
use Illuminate\Validation\ValidationException;

final class CreateFixedReservationHandler
{
    public function __construct(
        private FixedReservationRepository $fixedReservations,
        private CourtRepository $courts,
        private BranchRepository $branches,
        private FixedReservationDtoFactory $dtoFactory,
        private CustomerRepository $customers,
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

        $clubCustomer = $command->clubCustomerId !== null
            ? $this->customers->findClubCustomer($command->clubId, $command->clubCustomerId)
            : ($command->customerUserId !== null ? $this->customers->ensureForVerifiedUser($command->clubId, $command->customerUserId) : null);
        if ($command->clubCustomerId !== null && $clubCustomer === null) throw ValidationException::withMessages(['club_customer_id' => 'El cliente no pertenece al club.']);
        if ($clubCustomer !== null && ! $clubCustomer['active']) throw ValidationException::withMessages(['club_customer_id' => 'El cliente está inactivo en este club.']);

        return DB::transaction(
            function () use ($command, $clubCustomer) {
                $customerUserId = $clubCustomer['user_id'] ?? $command->customerUserId;
                $fixedReservation =
                    $this->fixedReservations->save(
                        new FixedReservation(
                            id: null,
                            clubId: $command->clubId,
                            customerUserId: $customerUserId,
                            guestName: $clubCustomer !== null && $customerUserId === null ? $clubCustomer['name'] : $command->guestName,
                            guestEmail: $clubCustomer !== null && $customerUserId === null ? $clubCustomer['email'] : $command->guestEmail,
                            guestPhone: $clubCustomer !== null && $customerUserId === null ? $clubCustomer['phone'] : $command->guestPhone,
                            createdByUserId: $command->createdByUserId,
                            startsOn: $command->startsOn,
                            endsOn: $command->endsOn,
                            active: true,
                            notes: $command->notes,
                            clubCustomerId: $clubCustomer['id'] ?? null,
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

                return $this->dtoFactory->create($fixedReservation, $savedSlots);
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
