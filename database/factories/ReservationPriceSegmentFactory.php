<?php

namespace Database\Factories;

use App\Models\CourtPriceRule;
use App\Models\Reservation;
use App\Models\ReservationPriceSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationPriceSegment>
 */
final class ReservationPriceSegmentFactory extends Factory
{
    protected $model = ReservationPriceSegment::class;

    public function definition(): array
    {
        $startsAt = now()
            ->addDay()
            ->setTime(17, 0);

        $endsAt = $startsAt->copy()->addHour();

        return [
            'reservation_id' => Reservation::factory(),

            'starts_at' => $startsAt,
            'ends_at' => $endsAt,

            'hourly_price' => '25000.00',
            'subtotal' => '25000.00',

            /*
             * Por defecto representa precio base.
             */
            'court_price_rule_id' => null,
            'rule_name' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Segmento de precio base
    |--------------------------------------------------------------------------
    */

    public function basePrice(
        string $hourlyPrice = '25000.00',
        ?string $subtotal = null,
    ): static {
        return $this->state(fn() => [
            'hourly_price' => $hourlyPrice,

            'subtotal' =>
            $subtotal ?? $hourlyPrice,

            'court_price_rule_id' => null,
            'rule_name' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Segmento promocional
    |--------------------------------------------------------------------------
    */

    public function promotional(
        CourtPriceRule $rule,
        string $hourlyPrice = '18000.00',
        ?string $subtotal = null,
    ): static {
        return $this->state(fn() => [
            'court_price_rule_id' => $rule->id,

            /*
             * Snapshot histórico.
             */
            'rule_name' => $rule->name,

            'hourly_price' => $hourlyPrice,

            'subtotal' =>
            $subtotal ?? $hourlyPrice,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Horario concreto
    |--------------------------------------------------------------------------
    */

    public function between(
        string $startsAt,
        string $endsAt
    ): static {
        return $this->state(fn() => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Valores calculados
    |--------------------------------------------------------------------------
    */

    public function withPricing(
        string $hourlyPrice,
        string $subtotal,
    ): static {
        return $this->state(fn() => [
            'hourly_price' => $hourlyPrice,
            'subtotal' => $subtotal,
        ]);
    }
}
