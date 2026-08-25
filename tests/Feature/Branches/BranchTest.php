<?php

namespace Tests\Feature\Branches;

use App\Models\Branch;
use App\Models\Club;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_con_membresia_global_ve_todas_las_sucursales(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch1 */
        $branch1 = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var Branch $branch2 */
        $branch2 = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var Role $role */
        $role = Role::factory()->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->global()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson("/api/clubs/{$club->id}/branches");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $branch1->id])
            ->assertJsonFragment(['id' => $branch2->id]);
    }

    public function test_usuario_ve_solo_las_sucursales_asignadas(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branchAsignada */
        $branchAsignada = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var Branch $branchSinAcceso */
        $branchSinAcceso = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var Role $role */
        $role = Role::factory()->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branchAsignada)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson("/api/clubs/{$club->id}/branches");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $branchAsignada->id])
            ->assertJsonMissing(['id' => $branchSinAcceso->id]);
    }

    public function test_usuario_puede_ver_una_sucursal_dentro_de_su_scope(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch */
        $branch = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('branch.view')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson("/api/branches/{$branch->id}");

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $branch->id,
            ]);
    }

    public function test_usuario_no_puede_ver_sucursal_fuera_de_su_scope(): void
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
        $role = Role::factory()
            ->withPermission('branch.view')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branchPermitida)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson("/api/branches/{$branchNoPermitida->id}");

        $response->assertForbidden();
    }

    public function test_usuario_puede_crear_sucursal_en_un_club_si_tiene_permiso_y_scope(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('branch.create')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->global()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson("/api/clubs/{$club->id}/branches", [
                'name' => 'Nueva sucursal',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('branches', [
            'club_id' => $club->id,
            'name' => 'Nueva sucursal',
        ]);
    }

    public function test_usuario_no_puede_crear_sucursal_en_otro_club(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $clubPermitido */
        $clubPermitido = Club::factory()->createOne();

        /** @var Club $otroClub */
        $otroClub = Club::factory()->createOne();

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('branch.create')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($clubPermitido)
            ->for($role)
            ->global()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson("/api/clubs/{$otroClub->id}/branches", [
                'name' => 'No debería crearse',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('branches', [
            'club_id' => $otroClub->id,
            'name' => 'No debería crearse',
        ]);
    }

    public function test_usuario_puede_actualizar_sucursal_con_permiso(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch */
        $branch = Branch::factory()
            ->for($club)
            ->createOne([
                'name' => 'Nombre original',
            ]);

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('branch.update')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson("/api/branches/{$branch->id}", [
                'name' => 'Nombre actualizado',
                'active' => true,
            ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Nombre actualizado',
        ]);
    }

    public function test_usuario_no_puede_actualizar_sucursal_fuera_de_scope(): void
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
        $role = Role::factory()
            ->withPermission('branch.update')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branchPermitida)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson("/api/branches/{$branchNoPermitida->id}", [
                'name' => 'No debería cambiar',
                'active' => true,
            ]);

        $response->assertForbidden();
    }

    public function test_usuario_puede_desactivar_sucursal_con_permiso(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch */
        $branch = Branch::factory()
            ->for($club)
            ->createOne([
                'active' => true,
            ]);

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('branch.deactivate')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->deleteJson("/api/branches/{$branch->id}");

        $response->assertSuccessful();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'active' => false,
        ]);
    }

    public function test_usuario_no_puede_desactivar_sucursal_sin_permiso(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch */
        $branch = Branch::factory()
            ->for($club)
            ->createOne([
                'active' => true,
            ]);

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('branch.view')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->deleteJson("/api/branches/{$branch->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'active' => true,
        ]);
    }
}
