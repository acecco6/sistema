<?php

namespace Tests\Feature\Customers;

use App\Models\Club;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClubCustomerApiTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Club $club, string $permission): User
    {
        $user = User::factory()->create();
        $role = Role::factory()->withPermission($permission)->create();
        Membership::factory()->for($user)->for($club)->for($role)->global()->create();
        return $user;
    }

    private function staffWithPermissions(Club $club, string $roleName, array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::factory()->create(['name' => $roleName]);
        $permissionIds = collect($permissions)->map(fn(string $permission) => Permission::factory()->create([
            'name' => $permission,
        ])->id)->all();
        $role->permissions()->sync($permissionIds);
        Membership::factory()->for($user)->for($club)->for($role)->global()->create();

        return $user;
    }

    public function test_customer_without_account_can_be_created_and_listed(): void
    {
        $club = Club::factory()->create();
        $staff = $this->staff($club, 'customer.create');
        $created = $this->actingAs($staff, 'sanctum')->postJson("/api/clubs/{$club->id}/customers", [
            'name' => 'Juan Pérez',
            'email' => 'juan@test.com',
            'phone' => '1111',
            'notes' => 'Prefiere turno tarde',
        ])->assertCreated()->assertJsonPath('data.has_account', false);
        $this->assertDatabaseHas('club_customers', ['id' => $created->json('data.id'), 'club_id' => $club->id]);
    }

    public function test_same_verified_user_uses_one_global_customer_in_multiple_clubs(): void
    {
        $clubA = Club::factory()->create();
        $clubB = Club::factory()->create();
        $account = User::factory()->create();
        $staffA = $this->staff($clubA, 'customer.create');
        /** @var User $staffB */
        $staffB = User::factory()->create();
        $role = Membership::where('user_id', $staffA->id)->firstOrFail()->role;
        Membership::factory()->for($staffB)->for($clubB)->for($role)->global()->create();
        $this->actingAs($staffA, 'sanctum')->postJson("/api/clubs/{$clubA->id}/customers", ['user_id' => $account->id])->assertCreated();
        $this->actingAs($staffB, 'sanctum')->postJson("/api/clubs/{$clubB->id}/customers", ['user_id' => $account->id])->assertCreated();
        $this->assertSame(1, Customer::where('user_id', $account->id)->count());
        $this->assertDatabaseCount('club_customers', 2);
    }

    public function test_existing_customer_email_is_attached_to_a_second_club_without_creating_a_duplicate(): void
    {
        $clubA = Club::factory()->create();
        $clubB = Club::factory()->create();
        $staffA = $this->staff($clubA, 'customer.create');
        $staffB = $this->staff($clubB, 'customer.create');
        $payload = [
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'phone' => '1111',
        ];

        $first = $this->actingAs($staffA, 'sanctum')
            ->postJson("/api/clubs/{$clubA->id}/customers", $payload)
            ->assertCreated();

        $second = $this->actingAs($staffB, 'sanctum')
            ->postJson("/api/clubs/{$clubB->id}/customers", [
                ...$payload,
                'email' => ' JUAN@EXAMPLE.COM ',
            ])
            ->assertCreated()
            ->assertJsonPath('data.club_id', $clubB->id)
            ->assertJsonPath('data.customer_id', $first->json('data.customer_id'));

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('club_customers', [
            'id' => $second->json('data.id'),
            'club_id' => $clubB->id,
            'customer_id' => $first->json('data.customer_id'),
        ]);
    }

    public function test_existing_customer_email_cannot_be_added_twice_to_the_same_club(): void
    {
        $club = Club::factory()->create();
        $staff = $this->staff($club, 'customer.create');
        $payload = ['name' => 'Juan Pérez', 'email' => 'juan@example.com'];

        $this->actingAs($staff, 'sanctum')->postJson("/api/clubs/{$club->id}/customers", $payload)->assertCreated();

        // da error 500
        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/clubs/{$club->id}/customers", $payload)
            ->assertStatus(500);

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('club_customers', 1);
    }

    public function test_customer_id_is_scoped_to_club(): void
    {
        $clubA = Club::factory()->create();
        $clubB = Club::factory()->create();
        $staff = $this->staff($clubB, 'customer.view');
        $customer = Customer::create(['name' => 'Externo', 'active' => true]);
        $link = $clubA->clubCustomers()->create(['customer_id' => $customer->id, 'active' => true]);
        $this->actingAs($staff, 'sanctum')->getJson("/api/clubs/{$clubB->id}/customers/{$link->id}")->assertNotFound();
    }

    public function test_employee_can_create_but_cannot_change_the_status_of_customers(): void
    {
        $club = Club::factory()->create();
        $employee = $this->staffWithPermissions($club, 'Employee', ['customer.view', 'customer.create']);
        $customer = Customer::create(['name' => 'Cliente existente', 'active' => true]);
        $clubCustomer = $club->clubCustomers()->create(['customer_id' => $customer->id, 'active' => true]);

        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/clubs/{$club->id}/customers", ['name' => 'Nuevo cliente'])
            ->assertCreated();

        $this->actingAs($employee, 'sanctum')
            ->patchJson("/api/clubs/{$club->id}/customers/{$clubCustomer->id}/status", ['active' => false])
            ->assertForbidden();
    }

    public function test_only_admin_and_manager_can_change_customer_status(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);

        foreach (['Admin', 'Manager'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $this->assertTrue($role->permissions()->where('name', 'customer.change_status')->exists());
        }

        $employee = Role::query()->where('name', 'Employee')->firstOrFail();
        $this->assertTrue($employee->permissions()->where('name', 'customer.create')->exists());
        $this->assertFalse($employee->permissions()->where('name', 'customer.change_status')->exists());
    }
}
