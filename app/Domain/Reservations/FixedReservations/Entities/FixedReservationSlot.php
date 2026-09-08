<?php

namespace App\Domain\Reservations\FixedReservations\Entities;

use InvalidArgumentException;

final class FixedReservationSlot
{
    public function __construct(
        private ?int $id,
        private int $fixedReservationId,
        private int $courtId,
        private int $dayOfWeek,
        private string $startTime,
        private int $durationMinutes,
        private bool $active = true,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (
            $this->dayOfWeek < 1
            || $this->dayOfWeek > 7
        ) {
            throw new InvalidArgumentException(
                'El día de la semana debe estar entre 1 y 7.'
            );
        }

        if ($this->durationMinutes < 60) {
            throw new InvalidArgumentException(
                'La duración mínima es de 60 minutos.'
            );
        }
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFixedReservationId(): int
    {
        return $this->fixedReservationId;
    }

    public function getCourtId(): int
    {
        return $this->courtId;
    }

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function getStartTime(): string
    {
        return $this->startTime;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }
}
