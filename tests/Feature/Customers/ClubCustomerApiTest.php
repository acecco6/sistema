<?php

namespace Tests\Feature\Customers;

use App\Models\Club;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
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

    public function test_customer_id_is_scoped_to_club(): void
    {
        $clubA = Club::factory()->create();
        $clubB = Club::factory()->create();
        $staff = $this->staff($clubB, 'customer.view');
        $customer = Customer::create(['name' => 'Externo', 'active' => true]);
        $link = $clubA->clubCustomers()->create(['customer_id' => $customer->id, 'active' => true]);
        $this->actingAs($staff, 'sanctum')->getJson("/api/clubs/{$clubB->id}/customers/{$link->id}")->assertNotFound();
    }
}
