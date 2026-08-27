<?php

namespace Database\Factories;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
final class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $startsAt = now()
            ->addDays(fake()->numberBetween(1, 20))
            ->setTime(
                fake()->numberBetween(8, 20),
                0
            );

        $endsAt = $startsAt->copy()->addHour();

        return [
            'court_id' => Court::factory(),

            /*
             * Por defecto generamos una reserva
             * de cliente registrado.
             */
            'customer_user_id' => User::factory(),

            'created_by_user_id' => null,

            'guest_name' => null,
            'guest_email' => null,
            'guest_phone' => null,

            'starts_at' => $startsAt,
            'ends_at' => $endsAt,

            'total_price' => fake()->randomElement([
                '15000.00',
                '18000.00',
                '20000.00',
                '25000.00',
                '30000.00',
            ]),

            'status' => ReservationStatus::CONFIRMED->value,

            'public_token' => (string) Str::uuid(),

            'notes' => null,
            'cancelled_at' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Estados
    |--------------------------------------------------------------------------
    */

    public function pending(): static
    {
        return $this->state(fn() => [
            'status' => ReservationStatus::PENDING->value,
            'cancelled_at' => null,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn() => [
            'status' => ReservationStatus::CONFIRMED->value,
            'cancelled_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn() => [
            'status' => ReservationStatus::CANCELLED->value,
            'cancelled_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn() => [
            'status' => ReservationStatus::COMPLETED->value,
            'cancelled_at' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Cliente registrado
    |--------------------------------------------------------------------------
    */

    public function forCustomer(
        User $customer
    ): static {
        return $this->state(fn() => [
            'customer_user_id' => $customer->id,

            'guest_name' => null,
            'guest_email' => null,
            'guest_phone' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Creada por personal
    |--------------------------------------------------------------------------
    */

    public function createdBy(
        User $user
    ): static {
        return $this->state(fn() => [
            'created_by_user_id' => $user->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Guest
    |--------------------------------------------------------------------------
    */

    public function guest(): static
    {
        return $this->state(fn() => [
            'customer_user_id' => null,

            'created_by_user_id' => null,

            'guest_name' => fake()->name(),

            'guest_email' => fake()->safeEmail(),

            'guest_phone' => fake()->phoneNumber(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Guest creado por empleado
    |--------------------------------------------------------------------------
    */

    public function guestCreatedBy(
        User $user
    ): static {
        return $this->state(fn() => [
            'customer_user_id' => null,

            'created_by_user_id' => $user->id,

            'guest_name' => fake()->name(),

            'guest_email' => fake()->safeEmail(),

            'guest_phone' => fake()->phoneNumber(),
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
    | Precio específico
    |--------------------------------------------------------------------------
    */

    public function withTotalPrice(
        string $price
    ): static {
        return $this->state(fn() => [
            'total_price' => $price,
        ]);
    }
}
