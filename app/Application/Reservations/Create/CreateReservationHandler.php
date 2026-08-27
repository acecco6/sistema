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

        /*
        |--------------------------------------------------------------------------
        | 1. Buscar Court
        |--------------------------------------------------------------------------
        */

        $court = $this->courts->findById($command->courtId);

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
        | 3. Validar horario / duración / disponibilidad
        |--------------------------------------------------------------------------
        |
        | ReservationValidator se encarga de:
        |
        | - startsAt < endsAt
        | - reserva futura
        | - horario de Branch
        | - alineación al intervalo
        | - duración múltiplo del intervalo
        | - overlap
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
        |
        | PriceResolver devuelve:
        |
        | ReservationPrice
        | ├── total
        | └── segments
        |
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
        |
        | Personal del club:
        | confirmed = true
        |
        | Invitado:
        | confirmed = false
        |
        */

        $status = $command->confirmed
            ? ReservationStatus::CONFIRMED
            : ReservationStatus::PENDING;


        /*
        |--------------------------------------------------------------------------
        | 6. Construir Reservation
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
        );


        /*
        |--------------------------------------------------------------------------
        | 7. Guardar todo dentro de una transaction
        |--------------------------------------------------------------------------
        |
        | Queremos que:
        |
        | Reservation
        | +
        | PriceSegments
        |
        | se guarden juntos.
        |
        | Si algo falla, se revierte todo.
        |
        */

        return DB::transaction(function () use ($reservation, $reservationPrice) {

            $savedReservation = $this->reservations->save($reservation);

            /*
            |--------------------------------------------------------------------------
            | 8. Convertir segmentos de Pricing a segmentos históricos
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
                | 9. Guardar snapshot del precio
                |--------------------------------------------------------------------------
                */

            $this->reservations->savePriceSegments(
                reservationId: $savedReservation->getId(),
                segments: $segments
            );

            return ReservationDto::fromDomain($savedReservation);
        });
    }
}
