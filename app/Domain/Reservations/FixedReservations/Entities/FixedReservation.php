<?php

namespace App\Domain\Reservations\FixedReservations\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

final class FixedReservation
{
    public function __construct(
        private ?int $id,
        private int $clubId,

        private ?int $customerUserId,

        private ?string $guestName,
        private ?string $guestEmail,
        private ?string $guestPhone,

        private ?int $createdByUserId,

        private DateTimeImmutable $startsOn,
        private ?DateTimeImmutable $endsOn,

        private bool $active = true,

        private ?string $notes = null,
    ) {
        $this->validateCustomer();
        $this->validateDates();
    }

    private function validateCustomer(): void
    {
        $hasRegisteredCustomer =
            $this->customerUserId !== null;

        $hasGuestCustomer =
            $this->guestName !== null
            || $this->guestEmail !== null
            || $this->guestPhone !== null;

        if (! $hasRegisteredCustomer && ! $hasGuestCustomer) {
            throw new InvalidArgumentException(
                'La reserva fija debe tener un cliente registrado o invitado.'
            );
        }

        if ($hasRegisteredCustomer && $hasGuestCustomer) {
            throw new InvalidArgumentException(
                'La reserva fija no puede tener simultáneamente un cliente registrado y un invitado.'
            );
        }

        if (
            ! $hasRegisteredCustomer
            && (
                $this->guestName === null
                || trim($this->guestName) === ''
            )
        ) {
            throw new InvalidArgumentException(
                'El nombre del invitado es obligatorio.'
            );
        }
    }

    private function validateDates(): void
    {
        if (
            $this->endsOn !== null
            && $this->endsOn < $this->startsOn
        ) {
            throw new InvalidArgumentException(
                'La fecha de finalización no puede ser anterior a la fecha de inicio.'
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

    public function isValidForDate(
        DateTimeImmutable $date
    ): bool {
        if (! $this->active) {
            return false;
        }

        if ($date < $this->startsOn) {
            return false;
        }

        if (
            $this->endsOn !== null
            && $date > $this->endsOn
        ) {
            return false;
        }

        return true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClubId(): int
    {
        return $this->clubId;
    }

    public function getCustomerUserId(): ?int
    {
        return $this->customerUserId;
    }

    public function getGuestName(): ?string
    {
        return $this->guestName;
    }

    public function getGuestEmail(): ?string
    {
        return $this->guestEmail;
    }

    public function getGuestPhone(): ?string
    {
        return $this->guestPhone;
    }

    public function getCreatedByUserId(): ?int
    {
        return $this->createdByUserId;
    }

    public function getStartsOn(): DateTimeImmutable
    {
        return $this->startsOn;
    }

    public function getEndsOn(): ?DateTimeImmutable
    {
        return $this->endsOn;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }
}
