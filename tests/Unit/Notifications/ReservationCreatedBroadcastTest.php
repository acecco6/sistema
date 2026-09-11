<?php

use App\Domain\Reservations\Entities\Reservation;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Events\ReservationCreated;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

describe('ReservationCreated broadcast', function () {
    test('se encola y se publica solamente despues del commit', function () {
        expect(is_subclass_of(ReservationCreated::class, ShouldBroadcast::class))->toBeTrue()
            ->and(is_subclass_of(ReservationCreated::class, ShouldDispatchAfterCommit::class))->toBeTrue();
    });

    test('publica en el canal global y en el canal de la sucursal con un payload seguro', function () {
        $reservation = new Reservation(
            id: 150,
            courtId: 9,
            customerUserId: null,
            createdByUserId: 3,
            guestName: 'Juan Pérez',
            guestEmail: 'juan@example.com',
            guestPhone: '111111111',
            startsAt: new DateTimeImmutable('2030-09-10 18:00:00'),
            endsAt: new DateTimeImmutable('2030-09-10 19:00:00'),
            totalPrice: '25000.00',
            status: ReservationStatus::PENDING,
            publicToken: 'public-token',
        );

        $event = new ReservationCreated(
            reservation: $reservation,
            clubId: 2,
            branchId: 8,
            courtName: 'Cancha 1',
            customerName: 'Juan Pérez',
        );

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(2)
            ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
            ->and($channels[0]->name)->toBe('private-club.2')
            ->and($channels[1])->toBeInstanceOf(PrivateChannel::class)
            ->and($channels[1]->name)->toBe('private-club.2.branch.8')
            ->and($event->broadcastAs())->toBe('reservation.created')
            ->and($event->broadcastWith())->toBe([
                'reservation_id' => 150,
                'club_id' => 2,
                'branch_id' => 8,
                'starts_at' => '2030-09-10 18:00:00',
                'ends_at' => '2030-09-10 19:00:00',
                'status' => ReservationStatus::PENDING->value,
                'total_price' => '25000.00',
                'court' => ['id' => 9, 'name' => 'Cancha 1'],
                'customer' => ['name' => 'Juan Pérez'],
            ]);

        expect($event->broadcastWith())->not->toHaveKeys([
            'guest_email',
            'guest_phone',
            'public_token',
        ]);
    });

    test('rechaza publicar una reserva que todavia no fue persistida', function () {
        $reservation = new Reservation(
            id: null,
            courtId: 9,
            customerUserId: null,
            createdByUserId: 3,
            guestName: 'Juan Pérez',
            guestEmail: null,
            guestPhone: '111111111',
            startsAt: new DateTimeImmutable('2030-09-10 18:00:00'),
            endsAt: new DateTimeImmutable('2030-09-10 19:00:00'),
            totalPrice: '25000.00',
            status: ReservationStatus::PENDING,
            publicToken: 'public-token',
        );

        expect(fn () => new ReservationCreated($reservation, 2, 8))
            ->toThrow(InvalidArgumentException::class);
    });
});
