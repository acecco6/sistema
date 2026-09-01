<?php

namespace App\Application\Reservations\DTOs;

use App\Application\Payments\DTOs\ReservationPaymentSummary;
use App\Domain\Reservations\Entities\Reservation;

final readonly class ReservationDetailsDto
{
    public function __construct(
        public ReservationDto $reservation,
        public ReservationPaymentSummary $paymentSummary,
    ) {}

    public static function fromDomain(
        Reservation $reservation,
        ReservationPaymentSummary $paymentSummary,
    ): self {
        return new self(
            reservation: ReservationDto::fromDomain($reservation),
            paymentSummary: $paymentSummary,
        );
    }

    public function toArray(): array
    {
        return [
            ...$this->reservation->toArray(),

            'payment_summary' => $this->paymentSummary->toArray(),
        ];
    }
}
