<?php

namespace Database\Factories;

use App\Models\TipoCourt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoCourt>
 */
final class TipoCourtFactory extends Factory
{
    protected $model = TipoCourt::class;

    public function definition(): array
    {
        return [
            'name' => 'Tipo ' . $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
        ];
    }
}
