<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
final class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'name' => fake()->company(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn() => [
            'active' => false,
        ]);
    }
}
