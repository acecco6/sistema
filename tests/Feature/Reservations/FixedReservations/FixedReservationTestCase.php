<?php

namespace Tests\Feature\Reservations\FixedReservations;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Branch;
use App\Models\Club;
use App\Models\Court;
use App\Models\CourtPrice;
use App\Models\FixedReservation;
use App\Models\FixedReservationSlot;
use App\Models\Reservation;
use App\Models\TipoCourt;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class FixedReservationTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     user: User,
     *     club: Club,
     *     branch: Branch,
     *     tipoCourt: TipoCourt,
     *     court: Court
     * }
     */
    protected function createCourtScenario(
        int $intervalMinutes = 60,
        string $price = '25000.00',
    ): array {
        $user = User::factory()->createOne([
            'active' => true,
        ]);

        $club = Club::factory()->createOne([
            'active' => true,
        ]);

        $branch = Branch::factory()
            ->for($club)
            ->createOne([
                'opening_time' => '08:00:00',
                'closing_time' => '23:59:59',
                'active' => true,
            ]);

        $tipoCourt = TipoCourt::factory()->createOne();

        $court = Court::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'active' => true,
        ]);

        DB::table('interval_time_tipo_court')->insert([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'interval_minutes' => $intervalMinutes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => $price,
            'active' => true,
        ]);

        return compact(
            'user',
            'club',
            'branch',
            'tipoCourt',
            'court',
        );
    }

    protected function createFixedReservation(
        Club $club,
        User $createdBy,
        DateTimeImmutable $startsOn,
        ?DateTimeImmutable $endsOn = null,
    ): FixedReservation {
        return FixedReservation::query()->create([
            'club_id' => $club->id,
            'customer_user_id' => null,
            'guest_name' => 'Cliente Reserva Fija',
            'guest_email' => 'cliente@example.com',
            'guest_phone' => '111111111',
            'created_by_user_id' => $createdBy->id,
            'starts_on' => $startsOn->format('Y-m-d'),
            'ends_on' => $endsOn?->format('Y-m-d'),
            'active' => true,
            'notes' => 'Reserva fija de test',
        ]);
    }

    protected function createFixedSlot(
        FixedReservation $fixedReservation,
        Court $court,
        int $dayOfWeek,
        string $startTime = '18:00:00',
        int $durationMinutes = 60,
    ): FixedReservationSlot {
        return FixedReservationSlot::query()->create([
            'fixed_reservation_id' => $fixedReservation->id,
            'court_id' => $court->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'duration_minutes' => $durationMinutes,
            'active' => true,
        ]);
    }

    protected function createBlockingReservation(
        Court $court,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
    ): Reservation {
        return Reservation::query()->create([
            'court_id' => $court->id,
            'customer_user_id' => null,
            'created_by_user_id' => null,
            'guest_name' => 'Reserva común bloqueante',
            'guest_email' => 'bloqueo@example.com',
            'guest_phone' => '222222222',
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
            'total_price' => '25000.00',
            'status' => ReservationStatus::CONFIRMED->value,
            'public_token' => (string) Str::uuid(),
            'notes' => 'Bloqueo de test',
            'cancelled_at' => null,
            'expires_at' => null,
            'fixed_reservation_slot_id' => null,
            'recurrence_date' => null,
        ]);
    }
}
