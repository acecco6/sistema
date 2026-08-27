<?php

namespace Tests\Feature\Pricing;

use App\Models\Branch;
use App\Models\Club;
use App\Models\CourtPrice;
use App\Models\Membership;
use App\Models\Role;
use App\Models\TipoCourt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CourtPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_con_scope_y_permiso_puede_crear_precio(): void
    {
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

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('court_price.create')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson("/api/branches/{$branch->id}/prices", [
                'tipo_court_id' => $tipoCourt->id,
                'price' => '25000.00',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('court_prices', [
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
            'active' => true,
        ]);
    }

    public function test_usuario_no_puede_crear_precio_fuera_de_su_scope(): void
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

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('court_price.create')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branchPermitida)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson("/api/branches/{$branchNoPermitida->id}/prices", [
                'tipo_court_id' => $tipoCourt->id,
                'price' => '25000.00',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('court_prices', [
            'branch_id' => $branchNoPermitida->id,
            'tipo_court_id' => $tipoCourt->id,
        ]);
    }

    public function test_membresia_global_puede_crear_precio_en_cualquier_sucursal_del_club(): void
    {
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

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('court_price.create')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->global()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson("/api/branches/{$branch->id}/prices", [
                'tipo_court_id' => $tipoCourt->id,
                'price' => '25000.00',
            ]);

        $response->assertCreated();
    }

    public function test_no_permite_dos_precios_para_misma_sucursal_y_tipo_de_cancha(): void
    {
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

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('court_price.create')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->global()
            ->createOne();

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
            'active' => true,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson("/api/branches/{$branch->id}/prices", [
                'tipo_court_id' => $tipoCourt->id,
                'price' => '30000.00',
            ]);

        /*
         * Si CourtPriceAlreadyExistsException usa 409,
         * dejá assertStatus(409).
         *
         * Si la estás traduciendo a otro status,
         * ajustá esta assertion.
         */
        $response->assertStatus(409);

        $this->assertDatabaseCount(
            'court_prices',
            1
        );
    }

    public function test_usuario_puede_ver_precios_de_sucursal_dentro_de_su_scope(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch */
        $branch = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var TipoCourt $tipo1 */
        $tipo1 = TipoCourt::factory()->createOne();

        /** @var TipoCourt $tipo2 */
        $tipo2 = TipoCourt::factory()->createOne();

        /** @var Role $role */
        $role = Role::factory()->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch)
            ->createOne();

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipo1->id,
            'price' => '25000.00',
        ]);

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipo2->id,
            'price' => '18000.00',
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson("/api/branches/{$branch->id}/prices");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_usuario_no_puede_ver_coleccion_de_precios_fuera_de_su_scope(): void
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
            ->getJson("/api/branches/{$branchNoPermitida->id}/prices");

        $response->assertForbidden();
    }

    public function test_usuario_con_permiso_puede_actualizar_precio(): void
    {
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
        ]);

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('court_price.update')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson("/api/court_prices/{$price->id}", [
                'price' => '30000.00',
            ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('court_prices', [
            'id' => $price->id,
            'price' => '30000.00',
        ]);
    }

    public function test_usuario_sin_permiso_no_puede_actualizar_precio(): void
    {
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
        ]);

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('court_price.view')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson("/api/court_prices/{$price->id}", [
                'price' => '30000.00',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('court_prices', [
            'id' => $price->id,
            'price' => '25000.00',
        ]);
    }

    public function test_usuario_con_permiso_puede_cambiar_estado_del_precio(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch */
        $branch = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var CourtPrice $price */
        $price = CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'active' => true,
        ]);

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('court_price.change_status')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/court_prices/{$price->id}/status",
                [
                    'active' => false,
                ]
            );

        $response->assertSuccessful();

        $this->assertDatabaseHas('court_prices', [
            'id' => $price->id,
            'active' => false,
        ]);
    }
}
