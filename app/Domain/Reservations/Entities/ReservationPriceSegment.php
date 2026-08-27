<?php

namespace App\Domain\Reservations\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

final class ReservationPriceSegment
{
    public function __construct(
        private ?int $id,
        private ?int $reservationId,

        private DateTimeImmutable $startsAt,
        private DateTimeImmutable $endsAt,

        private string $hourlyPrice,
        private string $subtotal,

        private ?int $courtPriceRuleId,
        private ?string $ruleName,
    ) {
        if ($this->endsAt <= $this->startsAt) {
            throw new InvalidArgumentException('El final del segmento debe ser posterior al inicio.');
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReservationId(): ?int
    {
        return $this->reservationId;
    }

    public function getStartsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getHourlyPrice(): string
    {
        return $this->hourlyPrice;
    }

    public function getSubtotal(): string
    {
        return $this->subtotal;
    }

    public function getCourtPriceRuleId(): ?int
    {
        return $this->courtPriceRuleId;
    }

    public function getRuleName(): ?string
    {
        return $this->ruleName;
    }

    public function minutes(): int
    {
        return (int) (
            (
                $this->endsAt->getTimestamp()
                - $this->startsAt->getTimestamp()
            ) / 60
        );
    }
}
