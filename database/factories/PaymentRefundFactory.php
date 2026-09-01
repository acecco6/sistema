<?php

namespace Database\Factories;

use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\RefundStatus;
use App\Models\PaymentRefund;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentRefund>
 */
final class PaymentRefundFactory extends Factory
{
    protected $model = PaymentRefund::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'payment_id' => null,
            'amount' => '10000.00',
            'status' => RefundStatus::PENDING,
            'reason' => 'Cancelación de reserva',
            'method' => null,
            'notes' => null,
            'created_by_user_id' => User::factory(),
            'completed_by_user_id' => null,
            'completed_at' => null,
        ];
    }

    public function forReservation(
        Reservation $reservation
    ): static {
        return $this->state(fn() => [
            'reservation_id' => $reservation->id,
        ]);
    }

    public function withAmount(string $amount): static
    {
        return $this->state(fn() => [
            'amount' => $amount,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn() => [
            'status' => RefundStatus::PENDING,
            'method' => null,
            'completed_by_user_id' => null,
            'completed_at' => null,
        ]);
    }

    public function completed(
        PaymentMethod $method = PaymentMethod::TRANSFER
    ): static {
        return $this->state(fn() => [
            'status' => RefundStatus::COMPLETED,
            'method' => $method,
            'completed_by_user_id' => User::factory(),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn() => [
            'status' => RefundStatus::CANCELLED,
            'method' => null,
            'completed_by_user_id' => null,
            'completed_at' => null,
        ]);
    }
}
