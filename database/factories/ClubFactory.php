<?php

namespace Database\Factories;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Club>
 */
final class ClubFactory extends Factory
{
    protected $model = Club::class;

    public function definition(): array
    {
        return [
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
