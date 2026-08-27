<?php

namespace Tests\Feature\Reservations;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Jobs\ExpirePendingReservationsJob;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReservationExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_vigente_bloquea_la_cancha(): void
    {
        Carbon::setTestNow('2030-09-10 12:00:00');

        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::PENDING->value,
            'starts_at' => '2030-09-10 18:00:00',
            'ends_at' => '2030-09-10 19:00:00',
            'expires_at' => '2030-09-10 12:15:00',
        ]);

        $repository = app(
            \App\Domain\Reservations\Repositories\ReservationRepository::class
        );

        $hasOverlap = $repository->hasOverlap(
            courtId: $reservation->court_id,
            startsAt: new \DateTimeImmutable('2030-09-10 18:30:00'),
            endsAt: new \DateTimeImmutable('2030-09-10 19:30:00'),
        );

        $this->assertTrue($hasOverlap);

        Carbon::setTestNow();
    }


    public function test_pending_vencida_no_bloquea_la_cancha(): void
    {
        Carbon::setTestNow('2030-09-10 12:30:00');

        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::PENDING->value,
            'starts_at' => '2030-09-10 18:00:00',
            'ends_at' => '2030-09-10 19:00:00',
            'expires_at' => '2030-09-10 12:15:00',
        ]);

        $repository = app(
            \App\Domain\Reservations\Repositories\ReservationRepository::class
        );

        $hasOverlap = $repository->hasOverlap(
            courtId: $reservation->court_id,
            startsAt: new \DateTimeImmutable('2030-09-10 18:30:00'),
            endsAt: new \DateTimeImmutable('2030-09-10 19:30:00'),
        );

        $this->assertFalse($hasOverlap);

        Carbon::setTestNow();
    }


    public function test_job_marca_pending_vencida_como_expired(): void
    {
        Carbon::setTestNow('2030-09-10 12:30:00');

        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::PENDING->value,
            'expires_at' => '2030-09-10 12:15:00',
        ]);

        app(ExpirePendingReservationsJob::class)->handle(
            app(
                \App\Domain\Reservations\Repositories\ReservationRepository::class
            )
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::EXPIRED->value,
        ]);

        Carbon::setTestNow();
    }


    public function test_job_no_expira_pending_que_sigue_vigente(): void
    {
        Carbon::setTestNow('2030-09-10 12:00:00');

        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::PENDING->value,
            'expires_at' => '2030-09-10 12:15:00',
        ]);

        app(ExpirePendingReservationsJob::class)->handle(
            app(
                \App\Domain\Reservations\Repositories\ReservationRepository::class
            )
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::PENDING->value,
        ]);

        Carbon::setTestNow();
    }
}
