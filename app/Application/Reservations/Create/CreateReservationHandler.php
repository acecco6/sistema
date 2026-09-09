<?php

namespace App\Application\Reservations\Create;


use App\Application\Pricing\Resolver\PriceResolver;
use App\Application\Reservations\DTOs\ReservationDto;
use App\Application\Reservations\DTOs\ReservationDtoFactory;
use App\Application\Reservations\Validation\ReservationValidator;
use App\Domain\Branches\Exceptions\BranchInactiveException;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Courts\Exceptions\CourtInactiveException;
use App\Domain\Courts\Exceptions\CourtNotFoundException;
use App\Domain\Courts\Repositories\CourtRepository;
use App\Domain\Reservations\Entities\Reservation;
use App\Domain\Reservations\Entities\ReservationPriceSegment;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Events\ReservationConfirmed;
use App\Domain\Reservations\Repositories\ReservationRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Application\Customers\Contracts\CustomerRepository;
use Illuminate\Validation\ValidationException;

final class CreateReservationHandler
{
    public function __construct(
        private CourtRepository $courts,
        private BranchRepository $branches,
        private ReservationRepository $reservations,
        private ReservationValidator $validator,
        private PriceResolver $priceResolver,
        private ReservationDtoFactory $dtoFactory,
        private CustomerRepository $customers,
    ) {}

    public function handle(CreateReservationCommand $command): ReservationDto
    {

        return DB::transaction(
            function () use ($command) {

                /*
                |--------------------------------------------------------------------------
                | 1. Bloquear la Court
                |--------------------------------------------------------------------------
                |
                | Toda creación de Reservation para esta Court tiene que pasar
                | por este mismo lock.
                |
                | Si otro request ya está creando una reserva para esta Court,
                | este request espera hasta que el anterior haga COMMIT/ROLLBACK.
                |
                */

                $court = $this->courts->findByIdForUpdate($command->courtId);

                if ($court === null) {
                    throw new CourtNotFoundException();
                }

                if (! $court->isActive()) {
                    throw new CourtInactiveException();
                }


                /*
                |--------------------------------------------------------------------------
                | 2. Buscar Branch
                |--------------------------------------------------------------------------
                */

                $branch = $this->branches->findById($court->getBranchId());

                if ($branch === null) {
                    throw new BranchNotFoundException();
                }

                if (! $branch->isActive()) {
                    throw new BranchInactiveException();
                }

                $clubCustomer = $command->clubCustomerId !== null
                    ? $this->customers->findClubCustomer($branch->getClubId(), $command->clubCustomerId)
                    : ($command->customerUserId !== null
                        ? $this->customers->ensureForVerifiedUser($branch->getClubId(), $command->customerUserId)
                        : null);

                if ($command->clubCustomerId !== null && $clubCustomer === null) {
                    throw ValidationException::withMessages(['club_customer_id' => 'El cliente no pertenece al club de la cancha.']);
                }
                if ($clubCustomer !== null && ! $clubCustomer['active']) {
                    throw ValidationException::withMessages(['club_customer_id' => 'El cliente está inactivo en este club.']);
                }

                $customerUserId = $clubCustomer['user_id'] ?? $command->customerUserId;
                $guestName = $clubCustomer !== null && $customerUserId === null ? $clubCustomer['name'] : $command->guestName;
                $guestEmail = $clubCustomer !== null && $customerUserId === null ? $clubCustomer['email'] : $command->guestEmail;
                $guestPhone = $clubCustomer !== null && $customerUserId === null ? $clubCustomer['phone'] : $command->guestPhone;


                /*
                |--------------------------------------------------------------------------
                | 3. Validar DESPUÉS de adquirir el lock
                |--------------------------------------------------------------------------
                |
                | Esto es fundamental.
                |
                | ReservationValidator consulta nuevamente:
                |
                | - horarios
                | - intervalos
                | - disponibilidad
                | - overlaps
                |
                | Como la Court está bloqueada, otro CreateReservationHandler
                | para la misma Court no puede adelantarse.
                |
                */

                $this->validator->validate(
                    court: $court,
                    branch: $branch,
                    startsAt: $command->startsAt,
                    endsAt: $command->endsAt,
                );


                /*
                |--------------------------------------------------------------------------
                | 4. Calcular precio
                |--------------------------------------------------------------------------
                */

                $reservationPrice = $this->priceResolver->resolve(
                    branchId: $branch->getId(),
                    tipoCourtId: $court->getTipoCourtId(),
                    startsAt: $command->startsAt,
                    endsAt: $command->endsAt,
                );


                /*
                |--------------------------------------------------------------------------
                | 5. Estado inicial
                |--------------------------------------------------------------------------
                */

                $status = $command->confirmed
                    ? ReservationStatus::CONFIRMED
                    : ReservationStatus::PENDING;


                $expiresAt = $status === ReservationStatus::PENDING
                    ? new DateTimeImmutable('+15 minutes')
                    : null;

                /*
                |--------------------------------------------------------------------------
                | 6. Crear entidad Reservation
                |--------------------------------------------------------------------------
                */

                $reservation = new Reservation(
                    id: null,
                    courtId: $command->courtId,
                    customerUserId: $customerUserId,
                    createdByUserId: $command->createdByUserId,
                    guestName: $guestName,
                    guestEmail: $guestEmail,
                    guestPhone: $guestPhone,
                    startsAt: $command->startsAt,
                    endsAt: $command->endsAt,
                    totalPrice: $reservationPrice->total,
                    status: $status,
                    publicToken: (string) Str::uuid(),
                    notes: $command->notes,
                    cancelledAt: null,
                    expiresAt: $expiresAt,
                    fixedReservationSlotId: $command->fixedReservationSlotId,
                    recurrenceDate: $command->recurrenceDate,
                    clubCustomerId: $clubCustomer['id'] ?? null,
                );


                /*
                |--------------------------------------------------------------------------
                | 7. Guardar Reservation
                |--------------------------------------------------------------------------
                */

                $savedReservation = $this->reservations->save($reservation);
                if ($savedReservation->getClubCustomerId() !== null) {
                    $this->customers->recordReservation($savedReservation->getClubCustomerId(), $savedReservation->getStartsAt()->format('Y-m-d H:i:s'));
                }


                /*
                |--------------------------------------------------------------------------
                | 8. Crear snapshot histórico de Pricing
                |--------------------------------------------------------------------------
                */

                $segments = array_map(
                    fn($segment) =>
                    new ReservationPriceSegment(
                        id: null,
                        reservationId: $savedReservation->getId(),
                        startsAt: $segment->startsAt,
                        endsAt: $segment->endsAt,
                        hourlyPrice: $segment->hourlyPrice,
                        subtotal: $segment->subtotal,
                        courtPriceRuleId: $segment->ruleId,
                        ruleName: $segment->ruleName,
                    ),

                    $reservationPrice->segments
                );


                /*
                |--------------------------------------------------------------------------
                | 9. Persistir segmentos
                |--------------------------------------------------------------------------
                */

                $this->reservations->savePriceSegments(
                    reservationId: $savedReservation->getId(),
                    segments: $segments
                );


                /*
                |--------------------------------------------------------------------------
                | 10. Devolver resultado
                |--------------------------------------------------------------------------
                |
                | Al salir correctamente de este callback:
                |
                | COMMIT
                |
                | y recién ahí se libera el lock de la Court.
                |
                */

                if ($savedReservation->getStatus() === ReservationStatus::CONFIRMED) {
                    ReservationConfirmed::dispatch(
                        $savedReservation->getId()
                    );
                }

                return $this->dtoFactory->create($savedReservation);
            },

            /*
             * Laravel puede reintentar la transaction
             * cuando detecta determinados deadlocks.
             */
            attempts: 3,
        );
    }
}
