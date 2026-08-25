<?php

namespace Tests\Feature\Clubs;

use App\Models\Club;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClubTest extends TestCase
{
    use RefreshDatabase;
    private $baseUrl = '/api/clubs';

    public function test_usuario_ve_solo_los_clubes_de_sus_membresias(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club1 */
        $club1 = Club::factory()->createOne();

        /** @var Club $club2 */
        $club2 = Club::factory()->createOne();

        /** @var Club $clubSinAcceso */
        $clubSinAcceso = Club::factory()->createOne();

        /** @var Role $role */
        $role = Role::factory()->createOne();

        Membership::factory()
            ->for($user)
            ->for($club1)
            ->for($role)
            ->global()
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club2)
            ->for($role)
            ->global()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson($this->baseUrl);

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $club1->id])
            ->assertJsonFragment(['id' => $club2->id])
            ->assertJsonMissing(['id' => $clubSinAcceso->id]);
    }

    public function test_usuario_no_ve_clubes_sin_membresia(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson($this->baseUrl);

        $response->assertForbidden();
    }

    public function test_usuario_puede_ver_un_club_al_que_pertenece(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('club.view')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->global()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson($this->baseUrl . "/{$club->id}");

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $club->id,
            ]);
    }

    public function test_usuario_no_puede_ver_un_club_al_que_no_pertenece(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Club $otroClub */
        $otroClub = Club::factory()->createOne();

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('club.view')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->global()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson($this->baseUrl . "/{$otroClub->id}");

        $response->assertForbidden();
    }

    public function test_usuario_con_membresia_global_y_permiso_puede_actualizar_club(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne([
            'name' => 'Nombre original',
        ]);

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
            ->putJson($this->baseUrl . "/{$club->id}", [
                'name' => 'Nombre actualizado',
                'active' => true,
            ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('clubs', [
            'id' => $club->id,
            'name' => 'Nombre actualizado',
        ]);
    }

    public function test_usuario_sin_permiso_no_puede_actualizar_club(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('club.view')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->global()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->putJson($this->baseUrl . "/{$club->id}", [
                'name' => 'No debería cambiar',
                'active' => true,
            ]);

        $response->assertForbidden();
    }

    public function test_usuario_puede_desactivar_club_con_permiso(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne([
            'active' => true,
        ]);

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('club.deactivate')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->global()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->deleteJson($this->baseUrl . "/{$club->id}");

        $response->assertSuccessful();

        $this->assertDatabaseHas('clubs', [
            'id' => $club->id,
            'active' => false,
        ]);
    }

    public function test_usuario_no_puede_desactivar_club_sin_permiso(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne([
            'active' => true,
        ]);

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('club.view')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->global()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->deleteJson($this->baseUrl . "/{$club->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('clubs', [
            'id' => $club->id,
            'active' => true,
        ]);
    }
}
