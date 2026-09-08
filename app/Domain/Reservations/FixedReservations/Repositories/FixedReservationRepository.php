<?php

namespace App\Domain\Reservations\FixedReservations\Repositories;

use App\Domain\Reservations\FixedReservations\Entities\FixedReservation;
use App\Domain\Reservations\FixedReservations\Entities\FixedReservationSlot;
use DateTimeImmutable;

interface FixedReservationRepository
{
    public function findById(
        int $id
    ): ?FixedReservation;

    public function findActiveById(
        int $id
    ): ?FixedReservation;

    /**
     * @return FixedReservation[]
     */
    public function findByClubId(
        int $clubId
    ): array;

    /**
     * @return FixedReservation[]
     */
    public function findActiveByClubId(
        int $clubId
    ): array;

    /**
     * Series activas que pueden generar ocurrencias
     * dentro del período solicitado.
     *
     * @return FixedReservation[]
     */
    public function findActiveBetween(
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): array;

    public function save(
        FixedReservation $fixedReservation
    ): FixedReservation;

    public function update(
        FixedReservation $fixedReservation
    ): FixedReservation;

    /**
     * @return FixedReservationSlot[]
     */
    public function findSlotsByFixedReservationId(
        int $fixedReservationId
    ): array;

    /**
     * @return FixedReservationSlot[]
     */
    public function findActiveSlotsByFixedReservationId(
        int $fixedReservationId
    ): array;

    public function findSlotById(
        int $id
    ): ?FixedReservationSlot;

    public function saveSlot(
        FixedReservationSlot $slot
    ): FixedReservationSlot;

    public function updateSlot(
        FixedReservationSlot $slot
    ): FixedReservationSlot;
}
