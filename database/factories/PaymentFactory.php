<?php

namespace Database\Factories;

use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),

            'amount' => '10000.00',

            'method' => PaymentMethod::MERCADO_PAGO->value,

            'status' => PaymentStatus::PENDING->value,

            'provider' => 'mercadopago',

            'provider_preference_id' => 'pref_' . Str::uuid(),

            'provider_payment_id' => null,

            'external_reference' => 'PAY-' . Str::uuid(),

            'checkout_url' => fake()->url(),

            'created_by_user_id' => null,

            'paid_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn() => [
            'status' => PaymentStatus::PENDING->value,
            'provider_payment_id' => null,
            'paid_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn() => [
            'status' => PaymentStatus::APPROVED->value,
            'provider_payment_id' => (string) fake()->randomNumber(8, true),
            'paid_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn() => [
            'status' => PaymentStatus::REJECTED->value,
            'paid_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn() => [
            'status' => PaymentStatus::CANCELLED->value,
            'paid_at' => null,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn() => [
            'status' => PaymentStatus::REFUNDED->value,
        ]);
    }

    public function forReservation(
        Reservation $reservation
    ): static {
        return $this->state(fn() => [
            'reservation_id' => $reservation->id,
        ]);
    }

    public function withAmount(
        string $amount
    ): static {
        return $this->state(fn() => [
            'amount' => $amount,
        ]);
    }

    public function withExternalReference(
        string $externalReference
    ): static {
        return $this->state(fn() => [
            'external_reference' => $externalReference,
        ]);
    }

    public function createdBy(
        User $user
    ): static {
        return $this->state(fn() => [
            'created_by_user_id' => $user->id,
        ]);
    }
}
