<?php

namespace App\Domain\Reservations\FixedReservations\Entities;

use DateTimeImmutable;

final class FixedReservationConflict
{
    public function __construct(
        private ?int $id,
        private int $fixedReservationId,
        private int $fixedReservationSlotId,
        private int $courtId,
        private DateTimeImmutable $recurrenceDate,
        private DateTimeImmutable $startsAt,
        private DateTimeImmutable $endsAt,
        private string $reason,
        private ?string $message,
        private bool $resolved = false,
        private ?int $resolvedByUserId = null,
        private ?DateTimeImmutable $resolvedAt = null,
    ) {}

    public function resolve(
        ?int $userId = null,
        ?DateTimeImmutable $resolvedAt = null,
    ): void {
        if ($this->resolved) {
            return;
        }

        $this->resolved = true;
        $this->resolvedByUserId = $userId;
        $this->resolvedAt = $resolvedAt
            ?? new DateTimeImmutable();
    }

    public function reopen(
        string $reason,
        ?string $message = null,
    ): void {
        $this->reason = $reason;
        $this->message = $message;

        $this->resolved = false;
        $this->resolvedByUserId = null;
        $this->resolvedAt = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFixedReservationId(): int
    {
        return $this->fixedReservationId;
    }

    public function getFixedReservationSlotId(): int
    {
        return $this->fixedReservationSlotId;
    }

    public function getCourtId(): int
    {
        return $this->courtId;
    }

    public function getRecurrenceDate(): DateTimeImmutable
    {
        return $this->recurrenceDate;
    }

    public function getStartsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function getResolvedByUserId(): ?int
    {
        return $this->resolvedByUserId;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }
}
