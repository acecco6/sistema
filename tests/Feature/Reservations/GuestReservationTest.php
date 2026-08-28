<?php

namespace Tests\Feature\Reservations;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class GuestReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitado_puede_ver_reserva_con_token_valido(): void
    {
        /** @var Reservation $reservation */
        $reservation = Reservation::factory()
            ->guest()
            ->createOne();

        $response = $this->getJson(
            "/api/public/reservations/{$reservation->public_token}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.court_id', $reservation->court_id)
            ->assertJsonPath('data.guest_name', $reservation->guest_name)
            ->assertJsonPath('data.status', $reservation->status->value)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.public_token');
    }

    public function test_token_inexistente_devuelve_404(): void
    {
        $token = (string) Str::uuid();

        $response = $this->getJson(
            "/api/public/reservations/{$token}"
        );

        $response->assertNotFound();
    }

    public function test_invitado_puede_cancelar_con_mas_de_24_horas(): void
    {
        Carbon::setTestNow(
            '2030-09-09 10:00:00'
        );

        /** @var Reservation $reservation */
        $reservation = Reservation::factory()
            ->guest()
            ->confirmed()
            ->between(
                '2030-09-10 14:00:00',
                '2030-09-10 15:00:00'
            )
            ->createOne();

        $response = $this->patchJson(
            "/api/public/reservations/{$reservation->public_token}/cancel"
        );

        $response->assertOk();

        $this->assertDatabaseHas(
            'reservations',
            [
                'id' => $reservation->id,
                'status' =>
                ReservationStatus::CANCELLED->value,
            ]
        );
    }

    public function test_invitado_no_puede_cancelar_con_menos_de_24_horas(): void
    {
        Carbon::setTestNow(
            '2030-09-09 14:01:00'
        );

        /** @var Reservation $reservation */
        $reservation = Reservation::factory()
            ->guest()
            ->confirmed()
            ->between(
                '2030-09-10 14:00:00',
                '2030-09-10 15:00:00'
            )
            ->createOne();

        $response = $this->patchJson(
            "/api/public/reservations/{$reservation->public_token}/cancel"
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas(
            'reservations',
            [
                'id' => $reservation->id,
                'status' =>
                ReservationStatus::CONFIRMED->value,
            ]
        );
    }

    public function test_token_invalido_no_puede_cancelar(): void
    {
        $token = (string) Str::uuid();

        $response = $this->patchJson(
            "/api/public/reservations/{$token}/cancel"
        );

        $response->assertNotFound();
    }

    public function test_respuesta_publica_no_expone_datos_internos(): void
    {
        /** @var Reservation $reservation */
        $reservation = Reservation::factory()
            ->guest()
            ->createOne();

        $response = $this->getJson(
            "/api/public/reservations/{$reservation->public_token}"
        );

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.customer_user_id')
            ->assertJsonMissingPath('data.created_by_user_id')
            ->assertJsonMissingPath('data.guest_email')
            ->assertJsonMissingPath('data.guest_phone')
            ->assertJsonMissingPath('data.public_token');
    }

    public function test_token_de_cliente_registrado_no_puede_usarse_en_endpoint_publico(): void
    {
        /** @var Reservation $reservation */
        $reservation = Reservation::factory()
            ->createOne([
                'customer_user_id' => \App\Models\User::factory()->createOne()->id,
                'guest_name' => null,
                'guest_email' => null,
                'guest_phone' => null,
            ]);

        $response = $this->getJson(
            "/api/public/reservations/{$reservation->public_token}"
        );

        $response->assertNotFound();
    }
}
