<?php

namespace App\Domain\Reservations\Repositories;

use App\Domain\Reservations\Entities\Reservation;
use App\Domain\Reservations\Entities\ReservationPriceSegment;
use DateTimeImmutable;

interface ReservationRepository
{
    public function findById(int $id): ?Reservation;

    /**
     * @return Reservation[]
     */
    public function findByCourt(int $courtId): array;

    /**
     * Devuelve true si existe una reserva que bloquea
     * el período solicitado.
     */
    public function hasOverlap(int $courtId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, ?int $excludeReservationId = null): bool;

    public function save(Reservation $reservation): Reservation;

    public function update(Reservation $reservation): Reservation;

    /**
     * @param ReservationPriceSegment[] $segments
     */
    public function savePriceSegments(int $reservationId, array $segments): void;

    /**
     * @return ReservationPriceSegment[]
     */
    public function findPriceSegments(int $reservationId): array;

    public function findBlockingReservationsBetween(int $courtId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): array;

    /**
     * @return Reservation[]
     */
    public function findExpiredPending(): array;

    public function findFinishedConfirmed(): array;

    /**
     * @return Reservation[]
     */
    public function findByCustomerUser(int $customerUserId): array;

    public function findByPublicToken(string $token): ?Reservation;

    public function findByIdForUpdate(int $id): ?Reservation;
}
