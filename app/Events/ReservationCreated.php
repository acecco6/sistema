<?php

namespace App\Events;

use App\Domain\Reservations\Entities\Reservation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use InvalidArgumentException;

final class ReservationCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly int $reservationId;
    public readonly int $courtId;
    public readonly string $startsAt;
    public readonly string $endsAt;
    public readonly string $status;
    public readonly string $totalPrice;

    public function __construct(
        Reservation $reservation,
        public readonly int $clubId,
        public readonly int $branchId,
        public readonly ?string $courtName = null,
        public readonly ?string $customerName = null,
    ) {
        $reservationId = $reservation->getId();
        if ($reservationId === null) {
            throw new InvalidArgumentException('No se puede publicar una reserva que todavía no fue persistida.');
        }

        $this->reservationId = $reservationId;
        $this->courtId = $reservation->getCourtId();
        $this->startsAt = $reservation->getStartsAt()->format('Y-m-d H:i:s');
        $this->endsAt = $reservation->getEndsAt()->format('Y-m-d H:i:s');
        $this->status = $reservation->getStatus()->value;
        $this->totalPrice = $reservation->getTotalPrice();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("club.{$this->clubId}"),
            new PrivateChannel("club.{$this->clubId}.branch.{$this->branchId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reservation.created';
    }

    public function broadcastWith(): array
    {
        return [
            'reservation_id' => $this->reservationId,
            'club_id' => $this->clubId,
            'branch_id' => $this->branchId,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'status' => $this->status,
            'total_price' => $this->totalPrice,
            'court' => [
                'id' => $this->courtId,
                'name' => $this->courtName,
            ],
            'customer' => [
                'name' => $this->customerName,
            ],
        ];
    }
}
