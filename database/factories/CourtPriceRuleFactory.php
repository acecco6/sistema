<?php

namespace Database\Factories;

use App\Models\CourtPrice;
use App\Models\CourtPriceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourtPriceRule>
 */
final class CourtPriceRuleFactory extends Factory
{
    protected $model = CourtPriceRule::class;

    public function definition(): array
    {
        return [
            'court_price_id' => CourtPrice::factory(),

            'name' => $this->faker->randomElement([
                'Happy Hour',
                'Promo Mañana',
                'Promo Tarde',
                'Promo Nocturna',
                'Promo Fin de Semana',
            ]),

            // Precio promocional por 60 minutos
            'price' => $this->faker->randomElement([
                '10000.00',
                '12000.00',
                '15000.00',
                '18000.00',
                '20000.00',
            ]),

            'day_of_week' => null,
            'specific_date' => null,

            'start_time' => '14:00:00',
            'end_time' => '18:00:00',

            'priority' => 10,

            'starts_at' => null,
            'ends_at' => null,

            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn() => [
            'active' => false,
        ]);
    }

    public function forDay(int $dayOfWeek): static
    {
        return $this->state(fn() => [
            'day_of_week' => $dayOfWeek,
        ]);
    }

    public function forSpecificDate(string $date): static
    {
        return $this->state(fn() => [
            'specific_date' => $date,
        ]);
    }

    public function between(
        string $startTime,
        string $endTime
    ): static {
        return $this->state(fn() => [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    public function withPrice(string $price): static
    {
        return $this->state(fn() => [
            'price' => $price,
        ]);
    }

    public function withPriority(int $priority): static
    {
        return $this->state(fn() => [
            'priority' => $priority,
        ]);
    }

    public function validBetween(
        string $startsAt,
        string $endsAt
    ): static {
        return $this->state(fn() => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
