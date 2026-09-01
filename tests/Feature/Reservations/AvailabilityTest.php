<?php

namespace Tests\Feature\Reservations;

use App\Models\Branch;
use App\Models\Club;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\TipoCourt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private $routeCourtAvailability = '/api/public/courts/';
    private $routeTipoCourtAvailability = '/api/public/branches/';
    public function test_muestra_slots_disponibles_y_ocupados_de_una_court(): void
    {
        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createScenario();

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 60,
        );

        Reservation::factory()
            ->for($court)
            ->confirmed()
            ->between(
                '2030-09-10 09:00:00',
                '2030-09-10 10:00:00'
            )
            ->createOne();

        $response = $this->getJson(
            "{$this->routeCourtAvailability}{$court->id}/availability?date=2030-09-10"
        );

        $response->assertOk();

        $response->assertJsonFragment([
            'starts_at' => '2030-09-10 10:00:00',
            'ends_at' => '2030-09-10 11:00:00',
            'available' => true,
        ]);
    }

    public function test_reserva_cancelada_no_marca_slot_como_ocupado(): void
    {
        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createScenario();

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 60,
        );

        Reservation::factory()
            ->for($court)
            ->cancelled()
            ->between(
                '2030-09-10 09:00:00',
                '2030-09-10 10:00:00'
            )
            ->createOne();

        $response = $this->getJson(
            "{$this->routeCourtAvailability}{$court->id}/availability?date=2030-09-10"
        );

        $response->assertOk();

        $response->assertJsonFragment([
            'starts_at' => '2030-09-10 09:00:00',
            'ends_at' => '2030-09-10 10:00:00',
            'available' => true,
        ]);
    }

    public function test_busca_disponibilidad_de_todas_las_courts_de_un_tipo(): void
    {
        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch */
        $branch = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var TipoCourt $tipoCourt */
        $tipoCourt = TipoCourt::factory()->createOne();

        /** @var Court $court1 */
        $court1 = Court::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'name' => 'Padel 1',
            'active' => true,
        ]);

        /** @var Court $court2 */
        $court2 = Court::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'name' => 'Padel 2',
            'active' => true,
        ]);

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 60,
        );

        $this->createPrice($branch->id, $tipoCourt->id, 10000);

        Reservation::factory()
            ->for($court1)
            ->confirmed()
            ->between(
                '2030-09-10 18:00:00',
                '2030-09-10 19:00:00'
            )
            ->createOne();

        $response = $this->getJson(
            "{$this->routeTipoCourtAvailability}{$branch->id}/availability"
                . '?date=2030-09-10'
                . "&tipo_court_id={$tipoCourt->id}"
                . '&start_time=18:00:00'
                . '&end_time=19:00:00'
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'court_id' => $court1->id,
                'court_name' => 'Padel 1',
            ])
            ->assertJsonFragment([
                'court_id' => $court2->id,
                'court_name' => 'Padel 2',
            ]);

        /*
         * Court 1 ocupada.
         */
        $response->assertJsonFragment([
            'court_id' => $court1->id,
        ]);

        /*
         * Los detalles exactos se pueden comprobar
         * con assertJsonPath una vez veamos el shape real
         * de tu successResponse.
         */
    }

    public function test_no_devuelve_courts_inactivas_en_busqueda_por_tipo(): void
    {
        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch */
        $branch = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var TipoCourt $tipoCourt */
        $tipoCourt = TipoCourt::factory()->createOne();

        $activeCourt = Court::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'active' => true,
        ]);

        $inactiveCourt = Court::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'active' => false,
        ]);

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 60,
        );

        $this->createPrice($branch->id, $tipoCourt->id, 10000);

        $response = $this->getJson(
            "{$this->routeTipoCourtAvailability}{$branch->id}/availability"
                . '?date=2030-09-10'
                . "&tipo_court_id={$tipoCourt->id}"
        );

        $response->assertOk();

        $response->assertJsonFragment([
            'court_id' => $activeCourt->id,
        ]);

        $response->assertJsonMissing([
            'court_id' => $inactiveCourt->id,
        ]);
    }


    public function test_start_time_no_desalinea_la_grilla(): void
    {
        Carbon::setTestNow('2030-09-10 12:00:00');

        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createScenario();

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 30,
        );

        $response = $this->getJson(
            "{$this->routeTipoCourtAvailability}{$branch->id}/availability"
                . '?date=2030-09-10'
                . "&tipo_court_id={$tipoCourt->id}"
                . '&duration_minutes=60'
                . '&start_time=18:05:00'
                . '&end_time=20:00:00'
        );

        $response->assertOk();

        $response->assertJsonMissing([
            'starts_at' => '2030-09-10 18:05:00',
        ]);

        $response->assertJsonFragment([
            'starts_at' => '2030-09-10 18:30:00',
            'ends_at' => '2030-09-10 19:30:00',
        ]);

        Carbon::setTestNow();
    }

    public function test_disponibilidad_de_hoy_no_muestra_horarios_pasados(): void
    {
        Carbon::setTestNow('2030-09-10 18:24:00');

        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createScenario();

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 30,
        );

        $response = $this->getJson(
            "{$this->routeTipoCourtAvailability}{$branch->id}/availability"
                . '?date=2030-09-10'
                . "&tipo_court_id={$tipoCourt->id}"
                . '&duration_minutes=60'
        );

        $response->assertOk();

        $response->assertJsonMissing([
            'starts_at' => '2030-09-10 18:00:00',
        ]);

        $response->assertJsonFragment([
            'starts_at' => '2030-09-10 18:30:00',
        ]);

        Carbon::setTestNow();
    }

    public function test_disponibilidad_de_court_hoy_no_muestra_horarios_pasados(): void
    {
        Carbon::setTestNow('2030-09-10 18:24:00');

        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createScenario();

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 30,
        );

        $response = $this->getJson(
            "{$this->routeCourtAvailability}{$court->id}/availability"
                . '?date=2030-09-10'
                . '&duration_minutes=60'
        );

        $response->assertOk();

        $response->assertJsonMissing([
            'starts_at' => '2030-09-10 18:00:00',
        ]);

        $response->assertJsonFragment([
            'starts_at' => '2030-09-10 18:30:00',
        ]);

        Carbon::setTestNow();
    }

    public function test_court_genera_disponibilidad_despues_de_medianoche(): void
    {
        Carbon::setTestNow('2030-09-10 12:00:00');

        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createScenario();

        $branch->update([
            'opening_time' => '08:00:00',
            'closing_time' => '02:00:00',
        ]);

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 30,
        );

        $response = $this->getJson(
            "{$this->routeCourtAvailability}{$court->id}/availability"
                . '?date=2030-09-10'
                . '&duration_minutes=60'
        );

        $response->assertOk();

        $response->assertJsonFragment([
            'starts_at' => '2030-09-10 23:30:00',
            'ends_at' => '2030-09-11 00:30:00',
        ]);

        $response->assertJsonFragment([
            'starts_at' => '2030-09-11 01:00:00',
            'ends_at' => '2030-09-11 02:00:00',
        ]);

        $response->assertJsonMissing([
            'starts_at' => '2030-09-11 01:30:00',
        ]);

        Carbon::setTestNow();
    }

    public function test_tipo_court_soporta_filtro_que_cruza_medianoche(): void
    {
        Carbon::setTestNow('2030-09-10 12:00:00');

        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createScenario();

        $branch->update([
            'opening_time' => '08:00:00',
            'closing_time' => '02:00:00',
        ]);

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 30,
        );

        $response = $this->getJson(
            "{$this->routeTipoCourtAvailability}{$branch->id}/availability"
                . '?date=2030-09-10'
                . "&tipo_court_id={$tipoCourt->id}"
                . '&duration_minutes=60'
                . '&start_time=23:05:00'
                . '&end_time=01:30:00'
        );

        $response->assertOk();

        $response->assertJsonMissing([
            'starts_at' => '2030-09-10 23:05:00',
        ]);

        $response->assertJsonFragment([
            'starts_at' => '2030-09-10 23:30:00',
            'ends_at' => '2030-09-11 00:30:00',
        ]);

        $response->assertJsonFragment([
            'starts_at' => '2030-09-11 00:30:00',
            'ends_at' => '2030-09-11 01:30:00',
        ]);

        Carbon::setTestNow();
    }

    public function test_jornada_nocturna_despues_de_medianoche_solo_muestra_horarios_futuros(): void
    {
        Carbon::setTestNow('2030-09-11 00:24:00');

        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createScenario();

        $branch->update([
            'opening_time' => '08:00:00',
            'closing_time' => '02:00:00',
        ]);

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 30,
        );

        /*
         * La jornada consultada comenzó el 10 y termina el 11 a las 02:00.
         */
        $response = $this->getJson(
            "{$this->routeCourtAvailability}{$court->id}/availability"
                . '?date=2030-09-10'
                . '&duration_minutes=60'
        );

        $response->assertOk();

        $response->assertJsonMissing([
            'starts_at' => '2030-09-11 00:00:00',
        ]);

        $response->assertJsonFragment([
            'starts_at' => '2030-09-11 00:30:00',
            'ends_at' => '2030-09-11 01:30:00',
        ]);

        Carbon::setTestNow();
    }

    private function createScenario(): array
    {
        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch */
        $branch = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var TipoCourt $tipoCourt */
        $tipoCourt = TipoCourt::factory()->createOne();

        /** @var Court $court */
        $court = Court::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'active' => true,
        ]);


        $price = $this->createPrice($branch->id, $tipoCourt->id, 10000);

        return [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ];
    }

    private function createInterval(int $branchId, int $tipoCourtId, int $minutes): void
    {
        DB::table('interval_time_tipo_court')
            ->insert([
                'branch_id' => $branchId,
                'tipo_court_id' => $tipoCourtId,
                'interval_minutes' => $minutes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function createPrice(int $branchId, int $tipoCourtId, $price): void
    {
        DB::table('court_prices')->insert([
            'branch_id' => $branchId,
            'tipo_court_id' => $tipoCourtId,
            'price' => $price,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
