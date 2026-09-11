<?php

namespace App\Events;

use App\Domain\Reservations\Entities\Reservation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
        public readonly int $clubId,
        public readonly int $branchId,
        public readonly ?string $courtName = null,
        public readonly ?string $customerName = null,
    ) {}

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
            'reservation_id' => $this->reservation->getId(),
            'club_id' => $this->clubId,
            'branch_id' => $this->branchId,
            'starts_at' => $this->reservation->getStartsAt()?->format('Y-m-d H:i:s'),
            'ends_at' => $this->reservation->getEndsAt()?->format('Y-m-d H:i:s'),
            'status' => is_object($this->reservation->getStatus()) && property_exists($this->reservation->getStatus(), 'value')
                ? $this->reservation->getStatus()->value
                : (string) $this->reservation->getStatus(),
            'total_price' => $this->reservation->getTotalPrice(),
            'court' => [
                'id' => $this->reservation->getCourtId(),
                'name' => $this->courtName,
            ],
            'customer' => [
                'name' => $this->customerName ?? $this->reservation->getGuestName(),
            ],
        ];
    }
}
