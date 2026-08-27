<?php

namespace App\Domain\Pricing\Entities;

final class CourtPriceRule
{
    public function __construct(
        private ?int $id,
        private int $courtPriceId,
        private string $name,
        private string $price,
        private ?int $dayOfWeek,
        private ?string $specificDate,
        private ?string $startTime,
        private ?string $endTime,
        private int $priority,
        private ?string $startsAt,
        private ?string $endsAt,
        private bool $active,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function appliesTo(\DateTimeImmutable $date): bool
    {
        if (! $this->active) {
            return false;
        }

        if (
            $this->startsAt !== null
            && $date < new \DateTimeImmutable($this->startsAt)
        ) {
            return false;
        }

        if (
            $this->endsAt !== null
            && $date > new \DateTimeImmutable($this->endsAt)
        ) {
            return false;
        }

        if (
            $this->specificDate !== null
            && $date->format('Y-m-d') !== $this->specificDate
        ) {
            return false;
        }

        if (
            $this->dayOfWeek !== null
            && (int) $date->format('N') !== $this->dayOfWeek
        ) {
            return false;
        }

        $time = $date->format('H:i:s');

        if (
            $this->startTime !== null
            && $time < $this->startTime
        ) {
            return false;
        }

        /*
        * IMPORTANTE:
        *
        * endTime NO está incluido.
        *
        * Promo 14:00 → 18:00
        *
        * 17:59:59 ✅
        * 18:00:00 ❌
        */
        if (
            $this->endTime !== null
            && $time >= $this->endTime
        ) {
            return false;
        }

        return true;
    }


    public function getCourtPriceId(): int
    {
        return $this->courtPriceId;
    }

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function getSpecificDate(): ?string
    {
        return $this->specificDate;
    }

    public function getStartTime(): ?string
    {
        return $this->startTime;
    }

    public function getEndTime(): ?string
    {
        return $this->endTime;
    }

    public function getStartsAt(): ?string
    {
        return $this->startsAt;
    }

    public function getEndsAt(): ?string
    {
        return $this->endsAt;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function update(
        string $name,
        string $price,
        ?int $dayOfWeek,
        ?string $specificDate,
        ?string $startTime,
        ?string $endTime,
        int $priority,
        ?string $startsAt,
        ?string $endsAt,
    ): void {

        $this->name       = $name;
        $this->price      = $price;
        $this->dayOfWeek  = $dayOfWeek;
        $this->specificDate = $specificDate;
        $this->startTime  = $startTime;
        $this->endTime    = $endTime;
        $this->priority   = $priority;
        $this->startsAt   = $startsAt;
        $this->endsAt     = $endsAt;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}
