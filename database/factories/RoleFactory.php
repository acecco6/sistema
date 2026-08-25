<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
final class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
        ];
    }

    public function withPermission(string $permissionName): static
    {
        return $this->afterCreating(function (Role $role) use ($permissionName) {
            $permission = Permission::factory()->create([
                'name' => $permissionName,
            ]);

            $role->permissions()->attach($permission->id);
        });
    }
}
