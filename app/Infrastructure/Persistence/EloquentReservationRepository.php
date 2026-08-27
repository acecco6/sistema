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

    public function hasOverlap(int $courtId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, ?int $excludeReservationId = null,): bool
    {
        $query = EloquentReservation::query()->where('court_id', $courtId)

            /*
             * Solamente estados que bloquean disponibilidad.
             */
            ->whereIn('status', [ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value])

            /*
             * Regla de overlap:
             * existing.starts_at < new.ends_at
             * AND
             * existing.ends_at > new.starts_at
             */
            ->where('starts_at', '<', $endsAt->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $startsAt->format('Y-m-d H:i:s'));

        /*
         * Esto nos va a servir más adelante
         * si permitimos modificar una reserva.
         *
         * Evita que una reserva choque consigo misma.
         */
        if ($excludeReservationId !== null) {
            $query->where('id', '!=', $excludeReservationId);
        }

        return $query->exists();
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
