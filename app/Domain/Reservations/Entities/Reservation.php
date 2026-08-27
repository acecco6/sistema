<?php

namespace App\Domain\Reservations\Entities;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Exceptions\InvalidReservationCustomerException;
use App\Domain\Reservations\Exceptions\InvalidReservationStatusTransitionException;
use App\Domain\Reservations\Exceptions\ReservationAlreadyCancelledException;
use DateTimeImmutable;

final class Reservation
{
    public function __construct(
        private ?int $id,
        private int $courtId,

        private ?int $customerUserId,
        private ?int $createdByUserId,

        private ?string $guestName,
        private ?string $guestEmail,
        private ?string $guestPhone,

        private DateTimeImmutable $startsAt,
        private DateTimeImmutable $endsAt,

        private string $totalPrice,
        private ReservationStatus $status,

        private string $publicToken,

        private ?string $notes = null,
        private ?DateTimeImmutable $cancelledAt = null,
    ) {
        $this->validateCustomer();
    }

    /*
    |--------------------------------------------------------------------------
    | Identidad del cliente
    |--------------------------------------------------------------------------
    |
    | Una reserva puede pertenecer a:
    |
    | - un usuario registrado
    | - un invitado
    |
    | Nunca puede quedar sin cliente.
    |
    */

    private function validateCustomer(): void
    {
        $hasRegisteredCustomer = $this->customerUserId !== null;

        $hasGuestCustomer = ($this->guestName !== null || $this->guestEmail !== null || $this->guestPhone !== null);

        /*
         * No tenemos ningún cliente.
         */
        if (! $hasRegisteredCustomer && ! $hasGuestCustomer) {
            throw new InvalidReservationCustomerException(
                'La reserva debe tener un cliente registrado o invitado.'
            );
        }

        /*
         * No permitimos mezclar cliente registrado
         * con datos de invitado.
         */
        if ($hasRegisteredCustomer && $hasGuestCustomer) {
            throw new InvalidReservationCustomerException('La reserva no puede tener simultáneamente un cliente registrado y un invitado.');
        }

        /*
         * Si es guest, como mínimo necesitamos nombre.
         *
         * Más adelante podemos decidir si también
         * email o teléfono son obligatorios.
         */
        if (! $hasRegisteredCustomer && ($this->guestName === null || trim($this->guestName) === '')) {
            throw new InvalidReservationCustomerException('El nombre del invitado es obligatorio.');
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Estados
    |--------------------------------------------------------------------------
    */

    public function confirm(): void
    {
        if ($this->status === ReservationStatus::CONFIRMED) {
            return;
        }

        if ($this->status !== ReservationStatus::PENDING) {
            throw new InvalidReservationStatusTransitionException("No se puede confirmar una reserva con estado {$this->status->value}.");
        }

        $this->status = ReservationStatus::CONFIRMED;
    }

    public function cancel(?DateTimeImmutable $cancelledAt = null): void
    {
        if ($this->status === ReservationStatus::CANCELLED) {
            throw new ReservationAlreadyCancelledException();
        }

        if ($this->status === ReservationStatus::COMPLETED) {
            throw new InvalidReservationStatusTransitionException('Una reserva completada no puede cancelarse.');
        }

        $this->status = ReservationStatus::CANCELLED;
        $this->cancelledAt = $cancelledAt ?? new DateTimeImmutable();
    }

    public function complete(): void
    {
        if ($this->status !== ReservationStatus::CONFIRMED) {
            throw new InvalidReservationStatusTransitionException('Solamente una reserva confirmada puede completarse.');
        }

        $this->status =
            ReservationStatus::COMPLETED;
    }


    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function belongsToRegisteredCustomer(): bool
    {
        return $this->customerUserId !== null;
    }

    public function belongsToGuest(): bool
    {
        return $this->customerUserId === null;
    }

    public function blocksAvailability(): bool
    {
        return $this->status->blocksAvailability();
    }


    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourtId(): int
    {
        return $this->courtId;
    }

    public function getCustomerUserId(): ?int
    {
        return $this->customerUserId;
    }

    public function getCreatedByUserId(): ?int
    {
        return $this->createdByUserId;
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

    public function getStartsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getTotalPrice(): string
    {
        return $this->totalPrice;
    }

    public function getStatus(): ReservationStatus
    {
        return $this->status;
    }

    public function getPublicToken(): string
    {
        return $this->publicToken;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }
}
