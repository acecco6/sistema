<?php

namespace Tests\Feature\Reservations;

use App\Models\Branch;
use App\Models\Club;
use App\Models\Court;
use App\Models\CourtPrice;
use App\Models\CourtPriceRule;
use App\Models\ReservationPriceSegment;
use App\Models\TipoCourt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ReservationPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserva_guarda_precio_base_como_precio_historico(): void
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

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
            'active' => true,
        ]);

        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan Pérez',
                'phone' => '111111111',
                'starts_at' => '2030-09-10 14:00:00',
                'ends_at' => '2030-09-10 15:00:00',
            ]
        );

        $response->assertCreated();

        $reservationId = $response->json(
            'data.id'
        );

        /*
         * 1 hora × $25.000
         */
        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'court_id' => $court->id,
            'total_price' => '25000.00',
        ]);

        /*
         * PriceResolver debería haber generado
         * un único segmento de precio base.
         */
        $this->assertDatabaseHas(
            'reservation_price_segments',
            [
                'reservation_id' => $reservationId,
                'starts_at' => '2030-09-10 14:00:00',
                'ends_at' => '2030-09-10 15:00:00',
                'hourly_price' => '25000.00',
                'subtotal' => '25000.00',
                'court_price_rule_id' => null,
                'rule_name' => null,
            ]
        );

        $this->assertDatabaseCount(
            'reservation_price_segments',
            1
        );
    }

    public function test_reserva_completa_dentro_de_promocion_guarda_precio_promocional(): void
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

        /** @var CourtPrice $courtPrice */
        $courtPrice = CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
            'active' => true,
        ]);

        /** @var CourtPriceRule $promotion */
        $promotion = CourtPriceRule::factory()->createOne([
            'court_price_id' => $courtPrice->id,
            'name' => 'Happy Hour',
            'price' => '18000.00',

            /*
             * Sin day_of_week para que aplique
             * cualquier día.
             */
            'day_of_week' => null,
            'specific_date' => null,

            'start_time' => '14:00:00',
            'end_time' => '18:00:00',

            'priority' => 10,

            'starts_at' => null,
            'ends_at' => null,

            'active' => true,
        ]);

        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan Pérez',
                'phone' => '111111111',
                'starts_at' => '2030-09-10 15:00:00',
                'ends_at' => '2030-09-10 16:00:00',
            ]
        );

        $response->assertCreated();

        $reservationId = $response->json(
            'data.id'
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'total_price' => '18000.00',
        ]);

        $this->assertDatabaseHas(
            'reservation_price_segments',
            [
                'reservation_id' => $reservationId,
                'hourly_price' => '18000.00',
                'subtotal' => '18000.00',
                'court_price_rule_id' => $promotion->id,
                'rule_name' => 'Happy Hour',
            ]
        );

        $this->assertDatabaseCount(
            'reservation_price_segments',
            1
        );
    }

    public function test_reserva_que_sale_de_promocion_guarda_dos_segmentos(): void
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

        /** @var CourtPrice $courtPrice */
        $courtPrice = CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
            'active' => true,
        ]);

        /** @var CourtPriceRule $promotion */
        $promotion = CourtPriceRule::factory()->createOne([
            'court_price_id' => $courtPrice->id,
            'name' => 'Happy Hour',
            'price' => '18000.00',
            'day_of_week' => null,
            'specific_date' => null,
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
            'priority' => 10,
            'starts_at' => null,
            'ends_at' => null,
            'active' => true,
        ]);

        /*
         * 17 → 18 = promo    $18.000
         * 18 → 19 = normal   $25.000
         *
         * TOTAL = $43.000
         */
        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan Pérez',
                'phone' => '111111111',
                'starts_at' => '2030-09-10 17:00:00',
                'ends_at' => '2030-09-10 19:00:00',
            ]
        );

        $response->assertCreated();

        $reservationId = $response->json(
            'data.id'
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'total_price' => '43000.00',
        ]);

        /*
         * Segmento promocional.
         */
        $this->assertDatabaseHas(
            'reservation_price_segments',
            [
                'reservation_id' => $reservationId,
                'starts_at' => '2030-09-10 17:00:00',
                'ends_at' => '2030-09-10 18:00:00',
                'hourly_price' => '18000.00',
                'subtotal' => '18000.00',
                'court_price_rule_id' => $promotion->id,
                'rule_name' => 'Happy Hour',
            ]
        );

        /*
         * Segmento normal.
         */
        $this->assertDatabaseHas(
            'reservation_price_segments',
            [
                'reservation_id' => $reservationId,
                'starts_at' => '2030-09-10 18:00:00',
                'ends_at' => '2030-09-10 19:00:00',
                'hourly_price' => '25000.00',
                'subtotal' => '25000.00',
                'court_price_rule_id' => null,
                'rule_name' => null,
            ]
        );

        $this->assertSame(
            2,
            ReservationPriceSegment::query()
                ->where(
                    'reservation_id',
                    $reservationId
                )
                ->count()
        );
    }

    public function test_reserva_que_entra_en_promocion_calcula_parcialmente_cada_precio(): void
    {
        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createScenario();

        /*
         * Para poder reservar 13:30 → 14:30,
         * el intervalo debe permitir comenzar
         * a las :30.
         */
        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 30,
        );

        /** @var CourtPrice $courtPrice */
        $courtPrice = CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
            'active' => true,
        ]);

        /** @var CourtPriceRule $promotion */
        $promotion = CourtPriceRule::factory()->createOne([
            'court_price_id' => $courtPrice->id,
            'name' => 'Happy Hour',
            'price' => '18000.00',
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
            'priority' => 10,
            'active' => true,
        ]);

        /*
         * 13:30 → 14:00
         * 30 min normal:
         *
         * 25.000 × 30 / 60
         * = 12.500
         *
         * 14:00 → 14:30
         * 30 min promo:
         *
         * 18.000 × 30 / 60
         * = 9.000
         *
         * TOTAL = 21.500
         */
        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan Pérez',
                'phone' => '111111111',
                'starts_at' => '2030-09-10 13:30:00',
                'ends_at' => '2030-09-10 14:30:00',
            ]
        );

        $response->assertCreated();

        $reservationId = $response->json(
            'data.id'
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'total_price' => '21500.00',
        ]);

        $this->assertDatabaseHas(
            'reservation_price_segments',
            [
                'reservation_id' => $reservationId,
                'starts_at' => '2030-09-10 13:30:00',
                'ends_at' => '2030-09-10 14:00:00',
                'hourly_price' => '25000.00',
                'subtotal' => '12500.00',
                'court_price_rule_id' => null,
            ]
        );

        $this->assertDatabaseHas(
            'reservation_price_segments',
            [
                'reservation_id' => $reservationId,
                'starts_at' => '2030-09-10 14:00:00',
                'ends_at' => '2030-09-10 14:30:00',
                'hourly_price' => '18000.00',
                'subtotal' => '9000.00',
                'court_price_rule_id' => $promotion->id,
                'rule_name' => 'Happy Hour',
            ]
        );

        $this->assertSame(
            2,
            ReservationPriceSegment::query()
                ->where(
                    'reservation_id',
                    $reservationId
                )
                ->count()
        );
    }

    public function test_cambio_posterior_de_promocion_no_modifica_precio_historico_de_reserva(): void
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

        /** @var CourtPrice $courtPrice */
        $courtPrice = CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
        ]);

        /** @var CourtPriceRule $promotion */
        $promotion = CourtPriceRule::factory()->createOne([
            'court_price_id' => $courtPrice->id,
            'name' => 'Promo original',
            'price' => '18000.00',
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
            'active' => true,
        ]);

        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan',
                'phone' => '111111',
                'starts_at' => '2030-09-10 15:00:00',
                'ends_at' => '2030-09-10 16:00:00',
            ]
        );

        $response->assertCreated();

        $reservationId = $response->json(
            'data.id'
        );

        /*
         * Cambiamos la promoción DESPUÉS de creada
         * la reserva.
         */
        $promotion->update([
            'name' => 'Promo modificada',
            'price' => '10000.00',
        ]);

        /*
         * Reservation sigue conservando el precio
         * acordado originalmente.
         */
        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'total_price' => '18000.00',
        ]);

        /*
         * Y también conservamos el snapshot
         * del nombre y precio de aquella promo.
         */
        $this->assertDatabaseHas(
            'reservation_price_segments',
            [
                'reservation_id' => $reservationId,
                'hourly_price' => '18000.00',
                'subtotal' => '18000.00',
                'rule_name' => 'Promo original',
            ]
        );
    }


    private function createScenario(): array
    {
        /** @var Club $club */
        $club = Club::factory()->createOne();

        /*
         * IMPORTANTE:
         *
         * Necesitamos horarios compatibles con
         * todos los tests.
         *
         * Ajustá los nombres si tu tabla usa
         * otras columnas.
         */

        /** @var Branch $branch */
        $branch = Branch::factory()
            ->for($club)
            ->createOne([
                'opening_time' => '08:00:00',
                'closing_time' => '23:00:00',
            ]);

        /** @var TipoCourt $tipoCourt */
        $tipoCourt = TipoCourt::factory()
            ->createOne();

        /** @var Court $court */
        $court = Court::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'active' => true,
        ]);

        return [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ];
    }

    private function createInterval(
        int $branchId,
        int $tipoCourtId,
        int $minutes,
    ): void {
        DB::table('interval_time_tipo_court')
            ->insert([
                'branch_id' => $branchId,
                'tipo_court_id' => $tipoCourtId,
                'interval_minutes' => $minutes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
