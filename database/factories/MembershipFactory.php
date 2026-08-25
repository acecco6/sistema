<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Club;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
final class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'club_id' => Club::factory(),
            'rol_id' => Role::factory(),
            'branch_id' => null,
            'active' => true,
        ];
    }

    public function global(): static
    {
        return $this->state(fn() => [
            'branch_id' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn() => [
            'active' => false,
        ]);
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state(fn() => [
            'club_id' => $branch->club_id,
            'branch_id' => $branch->id,
        ]);
    }
}
