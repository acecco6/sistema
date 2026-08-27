<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CourtPrice;
use App\Models\TipoCourt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourtPrice>
 */
final class CourtPriceFactory extends Factory
{
    protected $model = CourtPrice::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'tipo_court_id' => TipoCourt::factory(),

            // Precio por 60 minutos
            'price' => $this->faker->randomElement([
                '15000.00',
                '18000.00',
                '20000.00',
                '22000.00',
                '25000.00',
                '30000.00',
            ]),

            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn() => [
            'active' => false,
        ]);
    }

    public function withPrice(string $price): static
    {
        return $this->state(fn() => [
            'price' => $price,
        ]);
    }
}
