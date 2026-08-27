<?php

namespace Tests\Feature\Pricing;

use App\Models\Branch;
use App\Models\Club;
use App\Models\CourtPrice;
use App\Models\CourtPriceRule;
use App\Models\Membership;
use App\Models\Role;
use App\Models\TipoCourt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CourtPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_con_scope_y_permiso_puede_crear_promocion(): void
    {
        [
            $user,
            $club,
            $branch,
            $price,
        ] = $this->createPricingScenario(
            permission: 'court_promotion.create'
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/court_prices/{$price->id}/promotions",
                [
                    'name' => 'Happy Hour',
                    'price' => '18000.00',
                    'day_of_week' => 2,
                    'specific_date' => null,
                    'start_time' => '14:00:00',
                    'end_time' => '18:00:00',
                    'priority' => 10,
                    'starts_at' => null,
                    'ends_at' => null,
                ]
            );

        $response->assertCreated();

        $this->assertDatabaseHas('court_price_rules', [
            'court_price_id' => $price->id,
            'name' => 'Happy Hour',
            'price' => '18000.00',
            'day_of_week' => 2,
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
            'priority' => 10,
            'active' => true,
        ]);
    }

    public function test_usuario_no_puede_crear_promocion_fuera_de_su_scope(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branchPermitida */
        $branchPermitida = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var Branch $branchNoPermitida */
        $branchNoPermitida = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var TipoCourt $tipoCourt */
        $tipoCourt = TipoCourt::factory()->createOne();

        /** @var CourtPrice $price */
        $price = CourtPrice::factory()->createOne([
            'branch_id' => $branchNoPermitida->id,
            'tipo_court_id' => $tipoCourt->id,
        ]);

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('court_promotion.create')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branchPermitida)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/court_prices/{$price->id}/promotions",
                [
                    'name' => 'No debería crearse',
                    'price' => '15000.00',
                    'start_time' => '14:00:00',
                    'end_time' => '18:00:00',
                    'priority' => 10,
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseMissing('court_price_rules', [
            'court_price_id' => $price->id,
            'name' => 'No debería crearse',
        ]);
    }

    public function test_no_permite_promocion_con_hora_final_anterior_a_la_inicial(): void
    {
        [
            $user,
            $club,
            $branch,
            $price,
        ] = $this->createPricingScenario(
            permission: 'court_promotion.create'
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/court_prices/{$price->id}/promotions",
                [
                    'name' => 'Promo inválida',
                    'price' => '18000.00',
                    'start_time' => '18:00:00',
                    'end_time' => '14:00:00',
                    'priority' => 10,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'end_time',
            ]);
    }

    public function test_no_permite_dia_de_semana_fuera_del_rango_valido(): void
    {
        [
            $user,
            $club,
            $branch,
            $price,
        ] = $this->createPricingScenario(
            permission: 'court_promotion.create'
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/court_prices/{$price->id}/promotions",
                [
                    'name' => 'Promo inválida',
                    'price' => '18000.00',
                    'day_of_week' => 8,
                    'start_time' => '14:00:00',
                    'end_time' => '18:00:00',
                    'priority' => 10,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'day_of_week',
            ]);
    }

    public function test_no_permite_precio_de_promocion_menor_o_igual_a_cero(): void
    {
        [
            $user,
            $club,
            $branch,
            $price,
        ] = $this->createPricingScenario(
            permission: 'court_promotion.create'
        );

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/court_prices/{$price->id}/promotions",
                [
                    'name' => 'Promo inválida',
                    'price' => '0.00',
                    'start_time' => '14:00:00',
                    'end_time' => '18:00:00',
                    'priority' => 10,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'price',
            ]);
    }

    public function test_usuario_puede_ver_promociones_del_precio_dentro_de_su_scope(): void
    {
        [
            $user,
            $club,
            $branch,
            $price,
        ] = $this->createPricingScenario();

        CourtPriceRule::factory()->createOne([
            'court_price_id' => $price->id,
            'name' => 'Promo 1',
        ]);

        CourtPriceRule::factory()->createOne([
            'court_price_id' => $price->id,
            'name' => 'Promo 2',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/court_prices/{$price->id}/promotions"
            );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_usuario_no_puede_ver_promociones_fuera_de_su_scope(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branchPermitida */
        $branchPermitida = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var Branch $branchNoPermitida */
        $branchNoPermitida = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var TipoCourt $tipoCourt */
        $tipoCourt = TipoCourt::factory()->createOne();

        /** @var CourtPrice $price */
        $price = CourtPrice::factory()->createOne([
            'branch_id' => $branchNoPermitida->id,
            'tipo_court_id' => $tipoCourt->id,
        ]);

        /** @var Role $role */
        $role = Role::factory()->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branchPermitida)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/court_prices/{$price->id}/promotions"
            );

        $response->assertForbidden();
    }

    public function test_usuario_con_permiso_puede_actualizar_promocion(): void
    {
        [
            $user,
            $club,
            $branch,
            $price,
        ] = $this->createPricingScenario(
            permission: 'court_promotion.update'
        );

        /** @var CourtPriceRule $promotion */
        $promotion = CourtPriceRule::factory()->createOne([
            'court_price_id' => $price->id,
            'name' => 'Promo original',
            'price' => '18000.00',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/court_promotions/{$promotion->id}",
                [
                    'name' => 'Promo actualizada',
                    'price' => '15000.00',
                    'day_of_week' => 2,
                    'specific_date' => null,
                    'start_time' => '14:00:00',
                    'end_time' => '18:00:00',
                    'priority' => 20,
                    'starts_at' => null,
                    'ends_at' => null,
                ]
            );

        $response->assertSuccessful();

        $this->assertDatabaseHas('court_price_rules', [
            'id' => $promotion->id,
            'name' => 'Promo actualizada',
            'price' => '15000.00',
            'priority' => 20,
        ]);
    }

    public function test_usuario_sin_permiso_no_puede_actualizar_promocion(): void
    {
        [
            $user,
            $club,
            $branch,
            $price,
        ] = $this->createPricingScenario(
            permission: 'court_promotion.view'
        );

        /** @var CourtPriceRule $promotion */
        $promotion = CourtPriceRule::factory()->createOne([
            'court_price_id' => $price->id,
            'name' => 'Promo original',
            'price' => '18000.00',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson(
                "/api/court_promotions/{$promotion->id}",
                [
                    'name' => 'No debería actualizarse',
                    'price' => '15000.00',
                    'priority' => 10,
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('court_price_rules', [
            'id' => $promotion->id,
            'name' => 'Promo original',
            'price' => '18000.00',
        ]);
    }

    public function test_usuario_con_permiso_puede_cambiar_estado_de_promocion(): void
    {
        [
            $user,
            $club,
            $branch,
            $price,
        ] = $this->createPricingScenario(
            permission: 'court_promotion.change_status'
        );

        /** @var CourtPriceRule $promotion */
        $promotion = CourtPriceRule::factory()->createOne([
            'court_price_id' => $price->id,
            'active' => true,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/court_promotions/{$promotion->id}/status",
                [
                    'active' => false,
                ]
            );

        $response->assertSuccessful();

        $this->assertDatabaseHas('court_price_rules', [
            'id' => $promotion->id,
            'active' => false,
        ]);
    }

    private function createPricingScenario(
        ?string $permission = null
    ): array {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch */
        $branch = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var TipoCourt $tipoCourt */
        $tipoCourt = TipoCourt::factory()->createOne();

        /** @var CourtPrice $price */
        $price = CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
            'active' => true,
        ]);

        /** @var Role $role */
        $role = $permission !== null
            ? Role::factory()
            ->withPermission($permission)
            ->createOne()
            : Role::factory()->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch)
            ->createOne();

        return [
            $user,
            $club,
            $branch,
            $price,
        ];
    }
}
