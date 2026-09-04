<?php

namespace Tests\Feature\Notifications;

use App\Application\Reservations\Confirm\ConfirmReservationCommand;
use App\Application\Reservations\Confirm\ConfirmReservationHandler;
use App\Domain\Reservations\Events\ReservationConfirmed;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class ReservationConfirmedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_evento_reservation_confirmed_puede_ser_despachado(): void
    {
        Event::fake([
            ReservationConfirmed::class,
        ]);

        ReservationConfirmed::dispatch(123);

        Event::assertDispatched(
            ReservationConfirmed::class,
            function (ReservationConfirmed $event) {
                return $event->reservationId === 123;
            }
        );
    }

    public function test_confirmar_reserva_dispara_evento_reservation_confirmed(): void
    {
        Event::fake([ReservationConfirmed::class]);

        $reservation = Reservation::factory()
            ->createOne([
                'status' => ReservationStatus::PENDING->value,
                'expires_at' => now()->addMinutes(10),
            ]);

        $handler = $this->app->make(ConfirmReservationHandler::class);

        $handler->handle(new ConfirmReservationCommand($reservation->id));

        Event::assertDispatched(ReservationConfirmed::class, function (ReservationConfirmed $event) use ($reservation) {
            return $event->reservationId === $reservation->id;
        });

        Event::assertDispatchedTimes(ReservationConfirmed::class, 1);
    }
}
