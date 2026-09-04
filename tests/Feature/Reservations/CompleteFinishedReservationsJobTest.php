<?php

namespace Tests\Feature\Reservations;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Events\ReservationCompleted;
use App\Domain\Reservations\Repositories\ReservationRepository;
use App\Jobs\CompleteFinishedReservationsJob;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class CompleteFinishedReservationsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_completa_una_reserva_confirmada_que_ya_finalizo(): void
    {
        Carbon::setTestNow('2026-09-04 20:00:00');

        Event::fake([
            ReservationCompleted::class,
        ]);

        $reservation = Reservation::factory()
            ->confirmed()
            ->between(
                '2026-09-04 18:00:00',
                '2026-09-04 19:00:00'
            )
            ->createOne();

        $this->executeJob();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::COMPLETED->value,
        ]);

        Event::assertDispatched(
            ReservationCompleted::class,
            function (ReservationCompleted $event) use ($reservation) {
                return $event->reservationId === $reservation->id;
            }
        );
    }

    public function test_no_completa_una_reserva_confirmada_que_todavia_no_finalizo(): void
    {
        Carbon::setTestNow('2026-09-04 18:00:00');

        Event::fake([
            ReservationCompleted::class,
        ]);

        $reservation = Reservation::factory()
            ->confirmed()
            ->between(
                '2026-09-04 19:00:00',
                '2026-09-04 20:00:00'
            )
            ->createOne();

        $this->executeJob();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CONFIRMED->value,
        ]);

        Event::assertNotDispatched(
            ReservationCompleted::class
        );
    }

    public function test_completa_una_reserva_exactamente_cuando_llega_su_hora_de_fin(): void
    {
        Carbon::setTestNow('2026-09-04 20:00:00');

        Event::fake([
            ReservationCompleted::class,
        ]);

        $reservation = Reservation::factory()
            ->confirmed()
            ->between(
                '2026-09-04 19:00:00',
                '2026-09-04 20:00:00'
            )
            ->createOne();

        $this->executeJob();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::COMPLETED->value,
        ]);

        Event::assertDispatchedTimes(
            ReservationCompleted::class,
            1
        );
    }

    public function test_no_completa_una_reserva_pending_aunque_haya_finalizado(): void
    {
        Carbon::setTestNow('2026-09-04 20:00:00');

        Event::fake([
            ReservationCompleted::class,
        ]);

        $reservation = Reservation::factory()
            ->pending()
            ->between(
                '2026-09-04 18:00:00',
                '2026-09-04 19:00:00'
            )
            ->createOne();

        $this->executeJob();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::PENDING->value,
        ]);

        Event::assertNotDispatched(
            ReservationCompleted::class
        );
    }

    public function test_no_completa_una_reserva_cancelada_aunque_haya_finalizado(): void
    {
        Carbon::setTestNow('2026-09-04 20:00:00');

        Event::fake([
            ReservationCompleted::class,
        ]);

        $reservation = Reservation::factory()
            ->cancelled()
            ->between(
                '2026-09-04 18:00:00',
                '2026-09-04 19:00:00'
            )
            ->createOne();

        $this->executeJob();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CANCELLED->value,
        ]);

        Event::assertNotDispatched(
            ReservationCompleted::class
        );
    }

    public function test_no_procesa_nuevamente_una_reserva_que_ya_esta_completed(): void
    {
        Carbon::setTestNow('2026-09-04 20:00:00');

        Event::fake([
            ReservationCompleted::class,
        ]);

        $reservation = Reservation::factory()
            ->completed()
            ->between(
                '2026-09-04 18:00:00',
                '2026-09-04 19:00:00'
            )
            ->createOne();

        $this->executeJob();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::COMPLETED->value,
        ]);

        Event::assertNotDispatched(
            ReservationCompleted::class
        );
    }

    public function test_completa_todas_las_reservas_confirmadas_que_finalizaron(): void
    {
        Carbon::setTestNow('2026-09-04 22:00:00');

        Event::fake([ReservationCompleted::class]);

        $reservation1 = Reservation::factory()
            ->confirmed()
            ->between(
                '2026-09-04 18:00:00',
                '2026-09-04 19:00:00'
            )
            ->createOne();

        $reservation2 = Reservation::factory()
            ->confirmed()
            ->between(
                '2026-09-04 20:00:00',
                '2026-09-04 21:00:00'
            )
            ->createOne();

        $reservationFuture = Reservation::factory()
            ->confirmed()
            ->between(
                '2026-09-04 22:30:00',
                '2026-09-04 23:30:00'
            )
            ->createOne();

        $this->executeJob();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation1->id,
            'status' => ReservationStatus::COMPLETED->value,
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation2->id,
            'status' => ReservationStatus::COMPLETED->value,
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservationFuture->id,
            'status' => ReservationStatus::CONFIRMED->value,
        ]);

        Event::assertDispatchedTimes(ReservationCompleted::class, 2);

        Event::assertDispatched(ReservationCompleted::class, fn(ReservationCompleted $event) => $event->reservationId === $reservation1->id);

        Event::assertDispatched(ReservationCompleted::class, fn(ReservationCompleted $event) => $event->reservationId === $reservation2->id);
    }

    private function executeJob(): void
    {
        app(CompleteFinishedReservationsJob::class)
            ->handle(
                app(ReservationRepository::class)
            );
    }
}
