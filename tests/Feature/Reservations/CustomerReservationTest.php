<?php

namespace Tests\Feature\Reservations;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class CustomerReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cliente_puede_cancelar_su_reserva_con_mas_de_24_horas(): void
    {
        Carbon::setTestNow(
            '2030-09-09 10:00:00'
        );

        /** @var User $customer */
        $customer = User::factory()->createOne();

        /** @var Court $court */
        $court = Court::factory()->createOne();

        /** @var Reservation $reservation */
        $reservation = Reservation::factory()
            ->for($court)
            ->forCustomer($customer)
            ->confirmed()
            ->between(
                '2030-09-10 14:00:00',
                '2030-09-10 15:00:00'
            )
            ->createOne();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->patchJson(
                "/api/me/reservations/{$reservation->id}/cancel"
            );

        $response->assertSuccessful();

        $this->assertDatabaseHas(
            'reservations',
            [
                'id' => $reservation->id,
                'status' =>
                ReservationStatus::CANCELLED->value,
            ]
        );
    }

    public function test_cliente_puede_cancelar_exactamente_24_horas_antes(): void
    {
        Carbon::setTestNow(
            '2030-09-09 14:00:00'
        );

        /** @var User $customer */
        $customer = User::factory()->createOne();

        /** @var Reservation $reservation */
        $reservation = Reservation::factory()
            ->forCustomer($customer)
            ->confirmed()
            ->between(
                '2030-09-10 14:00:00',
                '2030-09-10 15:00:00'
            )
            ->createOne();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->patchJson(
                "/api/me/reservations/{$reservation->id}/cancel"
            );

        $response->assertSuccessful();

        $this->assertDatabaseHas(
            'reservations',
            [
                'id' => $reservation->id,
                'status' =>
                ReservationStatus::CANCELLED->value,
            ]
        );
    }

    public function test_cliente_no_puede_cancelar_con_menos_de_24_horas(): void
    {
        Carbon::setTestNow(
            '2030-09-09 14:01:00'
        );

        /** @var User $customer */
        $customer = User::factory()->createOne();

        /** @var Reservation $reservation */
        $reservation = Reservation::factory()
            ->forCustomer($customer)
            ->confirmed()
            ->between(
                '2030-09-10 14:00:00',
                '2030-09-10 15:00:00'
            )
            ->createOne();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->patchJson(
                "/api/me/reservations/{$reservation->id}/cancel"
            );

        $response
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' =>
                'La reserva solo puede cancelarse con al menos 24 horas de anticipación.',
            ]);

        $this->assertDatabaseHas(
            'reservations',
            [
                'id' => $reservation->id,
                'status' =>
                ReservationStatus::CONFIRMED->value,
            ]
        );
    }

    public function test_cliente_no_puede_cancelar_reserva_de_otro_cliente(): void
    {
        Carbon::setTestNow(
            '2030-09-09 10:00:00'
        );

        /** @var User $owner */
        $owner = User::factory()->createOne();

        /** @var User $otherCustomer */
        $otherCustomer = User::factory()->createOne();

        /** @var Reservation $reservation */
        $reservation = Reservation::factory()
            ->forCustomer($owner)
            ->confirmed()
            ->between(
                '2030-09-10 14:00:00',
                '2030-09-10 15:00:00'
            )
            ->createOne();

        $response = $this
            ->actingAs($otherCustomer, 'sanctum')
            ->patchJson(
                "/api/me/reservations/{$reservation->id}/cancel"
            );

        $response->assertNotFound();

        $this->assertDatabaseHas(
            'reservations',
            [
                'id' => $reservation->id,
                'status' =>
                ReservationStatus::CONFIRMED->value,
            ]
        );
    }

    public function test_usuario_no_autenticado_no_puede_cancelar_como_cliente(): void
    {
        /** @var Reservation $reservation */
        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne();

        $response = $this->patchJson(
            "/api/me/reservations/{$reservation->id}/cancel"
        );

        $response->assertUnauthorized();
    }
}
