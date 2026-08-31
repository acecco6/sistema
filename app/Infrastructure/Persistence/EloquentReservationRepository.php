<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Reservations\Entities\Reservation as DomainReservation;
use App\Domain\Reservations\Entities\ReservationPriceSegment as DomainReservationPriceSegment;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;
use App\Models\Reservation as EloquentReservation;
use App\Models\ReservationPriceSegment as EloquentReservationPriceSegment;
use DateTimeImmutable;

final class EloquentReservationRepository implements ReservationRepository
{
    public function findById(int $id): ?DomainReservation
    {
        $reservation = EloquentReservation::find($id);

        return $reservation ? $this->toDomain($reservation) : null;
    }

    public function findByPublicToken(string $token): ?DomainReservation
    {
        $reservation = EloquentReservation::query()->where('public_token', $token)->first();

        return $reservation ? $this->toDomain($reservation) : null;
    }

    public function findByCourt(int $courtId): array
    {
        return EloquentReservation::query()
            ->where('court_id', $courtId)
            ->orderBy('starts_at')
            ->get()
            ->map(
                fn(EloquentReservation $reservation) =>
                $this->toDomain($reservation)
            )
            ->all();
    }

    public function hasOverlap(int $courtId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, ?int $excludeReservationId = null): bool
    {
        return EloquentReservation::query()

            // Misma cancha
            ->where('court_id', $courtId)

            /*
        |--------------------------------------------------------------------------
        | Estados que realmente bloquean
        |--------------------------------------------------------------------------
        |
        | CONFIRMED:
        | siempre bloquea.
        |
        | PENDING:
        | solamente bloquea si todavía no venció.
        |
        */
            ->where(function ($query) {

                $query
                    ->where('status', ReservationStatus::CONFIRMED->value)
                    ->orWhere(function ($query) {
                        $query->where('status', ReservationStatus::PENDING->value)
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '>', now());
                    });
            })

            /*
        |--------------------------------------------------------------------------
        | Overlap real de horarios
        |--------------------------------------------------------------------------
        |
        | existente.starts_at < nuevo.ends_at
        | existente.ends_at   > nuevo.starts_at
        |
        | Esto permite reservas consecutivas:
        |
        | 18:00 - 19:00
        | 19:00 - 20:00 ✅
        |
        */
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)

            /*
        |--------------------------------------------------------------------------
        | Excluir una reserva concreta
        |--------------------------------------------------------------------------
        |
        | Útil si más adelante permitimos editar/reprogramar.
        |
        */
            ->when(
                $excludeReservationId !== null,
                fn($query) => $query->where('id', '!=', $excludeReservationId)
            )
            ->exists();
    }
    public function save(DomainReservation $reservation): DomainReservation
    {
        $eloquentReservation = EloquentReservation::create([
            'court_id' => $reservation->getCourtId(),
            'customer_user_id' => $reservation->getCustomerUserId(),
            'created_by_user_id' => $reservation->getCreatedByUserId(),
            'guest_name' => $reservation->getGuestName(),
            'guest_email' => $reservation->getGuestEmail(),
            'guest_phone' => $reservation->getGuestPhone(),
            'starts_at' => $reservation->getStartsAt()->format('Y-m-d H:i:s'),
            'ends_at' => $reservation->getEndsAt()->format('Y-m-d H:i:s'),
            'total_price' => $reservation->getTotalPrice(),
            'status' => $reservation->getStatus()->value,
            'public_token' => $reservation->getPublicToken(),
            'notes' => $reservation->getNotes(),
            'cancelled_at' => $reservation->getCancelledAt()?->format('Y-m-d H:i:s'),
            'expires_at' => $reservation->getExpiresAt()?->format('Y-m-d H:i:s'),
        ]);

        return $this->toDomain($eloquentReservation);
    }

    public function update(DomainReservation $reservation): DomainReservation
    {
        $eloquentReservation = EloquentReservation::find(
            $reservation->getId()
        );

        if ($eloquentReservation === null) {
            throw new ReservationNotFoundException();
        }

        $eloquentReservation->update([
            'customer_user_id' => $reservation->getCustomerUserId(),
            'created_by_user_id' => $reservation->getCreatedByUserId(),
            'guest_name' => $reservation->getGuestName(),
            'guest_email' => $reservation->getGuestEmail(),
            'guest_phone' => $reservation->getGuestPhone(),
            'starts_at' => $reservation->getStartsAt()->format('Y-m-d H:i:s'),
            'ends_at' => $reservation->getEndsAt()->format('Y-m-d H:i:s'),
            'total_price' => $reservation->getTotalPrice(),
            'status' => $reservation->getStatus()->value,
            'notes' => $reservation->getNotes(),
            'cancelled_at' => $reservation->getCancelledAt()?->format('Y-m-d H:i:s'),
        ]);

        return $this->toDomain($eloquentReservation->refresh());
    }

    public function savePriceSegments(int $reservationId, array $segments): void
    {
        foreach ($segments as $segment) {
            EloquentReservationPriceSegment::create([
                'reservation_id' => $reservationId,
                'starts_at' => $segment->getStartsAt()->format('Y-m-d H:i:s'),
                'ends_at' => $segment->getEndsAt()->format('Y-m-d H:i:s'),
                'hourly_price' => $segment->getHourlyPrice(),
                'subtotal' => $segment->getSubtotal(),
                'court_price_rule_id' => $segment->getCourtPriceRuleId(),
                'rule_name' => $segment->getRuleName(),
            ]);
        }
    }

    public function findPriceSegments(int $reservationId): array
    {
        return EloquentReservationPriceSegment::query()
            ->where('reservation_id', $reservationId)
            ->orderBy('starts_at')
            ->get()
            ->map(fn(EloquentReservationPriceSegment $segment) => $this->toDomainSegment($segment))
            ->all();
    }

    private function toDomain(EloquentReservation $reservation): DomainReservation
    {
        return new DomainReservation(
            id: $reservation->id,
            courtId: $reservation->court_id,
            customerUserId: $reservation->customer_user_id,
            createdByUserId: $reservation->created_by_user_id,
            guestName: $reservation->guest_name,
            guestEmail: $reservation->guest_email,
            guestPhone: $reservation->guest_phone,
            startsAt: new DateTimeImmutable($reservation->starts_at->format('Y-m-d H:i:s')),
            endsAt: new DateTimeImmutable($reservation->ends_at->format('Y-m-d H:i:s')),
            totalPrice: $reservation->total_price,
            status: $reservation->status,
            publicToken: $reservation->public_token,
            notes: $reservation->notes,
            cancelledAt: $reservation->cancelled_at ? new DateTimeImmutable($reservation->cancelled_at->format('Y-m-d H:i:s')) : null,
            expiresAt: $reservation->expires_at ? new DateTimeImmutable($reservation->expires_at->format('Y-m-d H:i:s')) : null,
        );
    }

    public function findBlockingReservationsBetween(int $courtId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array
    {
        return EloquentReservation::query()
            ->where('court_id', $courtId)
            ->whereIn('status', [ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value])
            ->where('starts_at', '<', $endsAt->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $startsAt->format('Y-m-d H:i:s'))
            ->orderBy('starts_at')
            ->get()
            ->map(fn(EloquentReservation $reservation) => $this->toDomain($reservation))
            ->all();
    }

    public function findExpiredPending(): array
    {
        return EloquentReservation::query()
            ->where('status', ReservationStatus::PENDING->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get()
            ->map(
                fn(EloquentReservation $model) =>
                $this->toDomain($model)
            )
            ->all();
    }

    public function findByCustomerUser(int $customerUserId): array
    {
        return EloquentReservation::query()
            ->where('customer_user_id', $customerUserId)
            ->orderByDesc('starts_at')
            ->get()
            ->map(
                fn(EloquentReservation $reservation) =>
                $this->toDomain($reservation)
            )
            ->all();
    }

    public function findByIdForUpdate(int $id): ?DomainReservation
    {
        $model = EloquentReservation::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();

        return $model
            ? $this->toDomain($model)
            : null;
    }


    private function toDomainSegment(EloquentReservationPriceSegment $segment): DomainReservationPriceSegment
    {
        return new DomainReservationPriceSegment(
            id: $segment->id,
            reservationId: $segment->reservation_id,
            startsAt: new DateTimeImmutable($segment->starts_at->format('Y-m-d H:i:s')),
            endsAt: new DateTimeImmutable($segment->ends_at->format('Y-m-d H:i:s')),
            hourlyPrice: $segment->hourly_price,
            subtotal: $segment->subtotal,
            courtPriceRuleId: $segment->court_price_rule_id,
            ruleName: $segment->rule_name,
        );
    }
}
