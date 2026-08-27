<?php

namespace App\Application\Reservations\Create;


use App\Application\Pricing\Resolver\PriceResolver;
use App\Application\Reservations\DTOs\ReservationDto;
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
use App\Domain\Reservations\Repositories\ReservationRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateReservationHandler
{
    public function __construct(
        private CourtRepository $courts,
        private BranchRepository $branches,
        private ReservationRepository $reservations,
        private ReservationValidator $validator,
        private PriceResolver $priceResolver,
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
                    customerUserId: $command->customerUserId,
                    createdByUserId: $command->createdByUserId,
                    guestName: $command->guestName,
                    guestEmail: $command->guestEmail,
                    guestPhone: $command->guestPhone,
                    startsAt: $command->startsAt,
                    endsAt: $command->endsAt,
                    totalPrice: $reservationPrice->total,
                    status: $status,
                    publicToken: (string) Str::uuid(),
                    notes: $command->notes,
                    cancelledAt: null,
                    expiresAt: $expiresAt,
                );


                /*
                |--------------------------------------------------------------------------
                | 7. Guardar Reservation
                |--------------------------------------------------------------------------
                */

                $savedReservation = $this->reservations->save($reservation);


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

                return ReservationDto::fromDomain($savedReservation);
            },

            /*
             * Laravel puede reintentar la transaction
             * cuando detecta determinados deadlocks.
             */
            attempts: 3,
        );
    }
}
