<?php

namespace App\Domain\Reservations\Enums;

enum ReservationStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    /*
     * Determina si este estado debe ocupar
     * disponibilidad de una Court.
     */
    public function blocksAvailability(): bool
    {
        return match ($this) {
            self::PENDING,
            self::CONFIRMED => true,

            self::CANCELLED,
            self::COMPLETED => false,
        };
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function isConfirmed(): bool
    {
        return $this === self::CONFIRMED;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }
}
