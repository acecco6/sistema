<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Court;
use App\Models\TipoCourt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Court>
 */
final class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'tipo_court_id' => TipoCourt::factory(),
            'name' => 'Cancha ' . $this->faker->unique()->numberBetween(1, 1000),
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
