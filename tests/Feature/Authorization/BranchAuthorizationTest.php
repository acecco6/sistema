<?php

namespace Tests\Feature\Authorization;

use App\Models\Branch;
use App\Models\Club;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_membresia_global_puede_ver_todas_las_sucursales_del_club(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

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
        $role = Role::factory()
            ->withPermission('branch.view')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson("/api/clubs/{$club->id}/branches");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_usuario_con_membresia_de_sucursal_solo_ve_la_sucursal_asignada(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

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
        $role = Role::factory()
            ->withPermission('branch.view')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch1)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson("/api/clubs/{$club->id}/branches");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJsonFragment([
            'id' => $branch1->id,
        ]);
    }

    public function test_usuario_con_dos_membresias_de_sucursal_ve_ambas_sucursales(): void
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
        $role = Role::factory()
            ->withPermission('branch.view')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch1)
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch2)
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

    public function test_usuario_no_puede_actualizar_una_sucursal_fuera_del_alcance_de_su_membresia(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

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
        $role = Role::factory()
            ->withPermission('branch.update')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch1)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson("/api/branches/{$branch2->id}", [
                'name' => 'Updated',
            ]);

        $response->assertForbidden();
    }

    public function test_usuario_puede_actualizar_una_sucursal_dentro_del_alcance_si_su_rol_tiene_permiso(): void
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
                'name' => 'Actualizada',
                'active' => true,
            ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Actualizada',
        ]);
    }

    public function test_usuario_con_alcance_correcto_pero_sin_permiso_no_puede_realizar_la_accion(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

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
            ->putJson("/api/branches/{$branch->id}", [
                'name' => 'No debería cambiar',
                'active' => true,
            ]);

        $response->assertForbidden();
    }

    public function test_membresia_inactiva_no_otorga_acceso(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch */
        $branch = Branch::factory()
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
            ->forBranch($branch)
            ->inactive()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson("/api/branches/{$branch->id}", [
                'name' => 'Updated',
                'active' => true,
            ]);

        $response->assertForbidden();
    }

    public function test_usuario_con_membresia_de_sucursal_no_puede_actualizar_el_club_completo(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch */
        $branch = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('club.update')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson("/api/clubs/{$club->id}", [
                'name' => 'Nuevo nombre',
                'active' => true,
            ]);

        $response->assertForbidden();
    }

    public function test_membresia_global_puede_actualizar_el_club_si_el_rol_tiene_permiso(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('club.update')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->global()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson("/api/clubs/{$club->id}", [
                'name' => 'Actualizado',
                'active' => true,
            ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('clubs', [
            'id' => $club->id,
            'name' => 'Actualizado',
        ]);
    }
}
