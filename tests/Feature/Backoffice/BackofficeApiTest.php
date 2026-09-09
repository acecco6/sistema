<?php

namespace Tests\Feature\Backoffice;

use App\Models\Branch;
use App\Models\Club;
use App\Models\Court;
use App\Models\Membership;
use App\Models\MercadoPagoAccount;
use App\Models\Permission;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\TipoCourt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BackofficeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_contexto_de_sesion_devuelve_memberships_roles_y_permisos_efectivos(): void
    {
        $user = User::factory()->createOne();
        $club = Club::factory()->createOne();
        $role = $this->roleWithPermissions(['branch.view', 'reservation.view']);

        Membership::factory()->for($user)->for($club)->for($role)->global()->createOne();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/context')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.memberships.0.club.id', $club->id)
            ->assertJsonPath('data.memberships.0.role.id', $role->id)
            ->assertJsonPath('data.memberships.0.scope', 'club')
            ->assertJsonPath('data.effective_permissions.0', 'branch.view')
            ->assertJsonPath('data.effective_permissions.1', 'reservation.view');
    }

    public function test_memberships_se_pueden_listar_filtrar_paginar_y_ver_en_detalle(): void
    {
        $admin = User::factory()->createOne();
        $club = Club::factory()->createOne();
        $branch = Branch::factory()->for($club)->createOne();
        $adminRole = $this->roleWithPermissions(['membership.view']);
        $employeeRole = Role::factory()->createOne(['name' => 'Employee']);
        Membership::factory()->for($admin)->for($club)->for($adminRole)->global()->createOne();

        $employee = User::factory()->createOne(['name' => 'Cliente Buscado']);
        $membership = Membership::factory()->for($employee)->for($employeeRole)->forBranch($branch)->createOne();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/clubs/{$club->id}/memberships?search=Buscado&branch_id={$branch->id}&per_page=10")
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $membership->id)
            ->assertJsonPath('data.items.0.user.name', 'Cliente Buscado')
            ->assertJsonPath('data.items.0.branch.id', $branch->id)
            ->assertJsonPath('data.pagination.total', 1);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/memberships/{$membership->id}")
            ->assertOk()
            ->assertJsonPath('data.role.id', $employeeRole->id);
    }

    public function test_catalogos_y_busqueda_de_usuarios_devuelven_solo_datos_publicos(): void
    {
        $admin = User::factory()->createOne();
        $club = Club::factory()->createOne();
        $role = $this->roleWithPermissions(['user.view']);
        Membership::factory()->for($admin)->for($club)->for($role)->global()->createOne();
        $candidate = User::factory()->createOne(['name' => 'María Cliente', 'email' => 'maria@example.com']);
        $type = TipoCourt::factory()->createOne(['name' => 'Pádel']);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/clubs/{$club->id}/users?search=María")
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $candidate->id)
            ->assertJsonMissingPath('data.items.0.password');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/roles')
            ->assertOk()
            ->assertJsonFragment(['id' => $role->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/roles/{$role->id}/permissions")
            ->assertOk()
            ->assertJsonFragment(['name' => 'user.view']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/court-types')
            ->assertOk()
            ->assertJsonFragment(['id' => $type->id, 'name' => 'Pádel']);
    }

    public function test_intervalo_se_consulta_y_actualiza_respetando_permisos(): void
    {
        $admin = User::factory()->createOne();
        $club = Club::factory()->createOne();
        $branch = Branch::factory()->for($club)->createOne();
        $type = TipoCourt::factory()->createOne();
        $role = $this->roleWithPermissions(['court_interval.view', 'court_interval.update']);
        Membership::factory()->for($admin)->for($club)->for($role)->forBranch($branch)->createOne();

        $url = "/api/branches/{$branch->id}/court-types/{$type->id}/interval";

        $this->actingAs($admin, 'sanctum')->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.interval_minutes', 30);

        $this->actingAs($admin, 'sanctum')->patchJson($url, ['interval_minutes' => 20])
            ->assertOk()
            ->assertJsonPath('data.interval_minutes', 20);

        $this->assertDatabaseHas('interval_time_tipo_court', [
            'branch_id' => $branch->id,
            'tipo_court_id' => $type->id,
            'interval_minutes' => 20,
        ]);
    }

    public function test_estado_de_mercado_pago_no_expone_tokens(): void
    {
        $admin = User::factory()->createOne();
        $club = Club::factory()->createOne();
        $role = $this->roleWithPermissions(['club.mercado_pago.view']);
        Membership::factory()->for($admin)->for($club)->for($role)->global()->createOne();
        MercadoPagoAccount::create([
            'club_id' => $club->id,
            'mercado_pago_user_id' => 'seller-1',
            'access_token' => 'access-secret',
            'refresh_token' => 'refresh-secret',
            'expires_at' => now()->addMonth(),
            'public_key' => 'public-key',
            'active' => true,
            'connected_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/clubs/{$club->id}/mercado-pago")
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonMissingPath('data.access_token')
            ->assertJsonMissingPath('data.refresh_token');
    }

    public function test_dashboard_y_reservation_dto_enriquecido(): void
    {
        $admin = User::factory()->createOne();
        $customer = User::factory()->createOne(['name' => 'Cliente Agenda']);
        $club = Club::factory()->createOne();
        $branch = Branch::factory()->for($club)->createOne(['opening_time' => '08:00:00', 'closing_time' => '22:00:00']);
        $court = Court::factory()->for($branch)->createOne();
        $role = $this->roleWithPermissions(['dashboard.view']);
        Membership::factory()->for($admin)->for($club)->for($role)->forBranch($branch)->createOne();
        $dashboardDate = now()->addDay()->startOfDay();
        $reservation = Reservation::factory()->for($court)->forCustomer($customer)->confirmed()->createOne([
            'starts_at' => $dashboardDate->copy()->addHours(18),
            'ends_at' => $dashboardDate->copy()->addHours(19),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/branches/{$branch->id}/dashboard?date=".$dashboardDate->toDateString())
            ->assertOk()
            ->assertJsonPath('data.reservations.total', 1)
            ->assertJsonPath('data.upcoming_reservations.0.customer.name', 'Cliente Agenda')
            ->assertJsonPath('data.upcoming_reservations.0.source', 'manual');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/branches/{$branch->id}/reservations?date=".$dashboardDate->toDateString())
            ->assertOk()
            ->assertJsonPath('data.courts.0.reservations.0.customer.name', 'Cliente Agenda')
            ->assertJsonPath('data.courts.0.reservations.0.guest', null)
            ->assertJsonPath('data.courts.0.reservations.0.source', 'manual')
            ->assertJsonPath('data.courts.0.reservations.0.fixed_reservation_slot_id', null);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
    }

    private function roleWithPermissions(array $names): Role
    {
        $role = Role::factory()->createOne();
        $permissions = collect($names)->map(fn (string $name) => Permission::factory()->createOne(['name' => $name]));
        $role->permissions()->attach($permissions->pluck('id'));

        return $role;
    }
}
