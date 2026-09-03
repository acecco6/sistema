<?php

namespace Tests\Feature\Reservations;

use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Branch;
use App\Models\Club;
use App\Models\Court;
use App\Models\CourtPrice;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\TipoCourt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_con_scope_y_permiso_puede_crear_reserva_confirmada(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createBaseScenario(
            permission: 'reservation.create'
        );

        $customer = User::factory()->createOne();

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 60,
        );

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
            'active' => true,
        ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/courts/{$court->id}/reservations",
                [
                    'guest_name' => $customer->name,
                    'guest_email' => $customer->email,
                    'customer_user_id' => $customer->id,
                    'starts_at' => '2030-09-10 14:00:00',
                    'ends_at' => '2030-09-10 15:00:00',
                    'confirmed' => true,
                ]
            );

        $response->assertCreated();

        $this->assertDatabaseHas('reservations', [
            'court_id' => $court->id,
            'customer_user_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'total_price' => '25000.00',
            'status' => ReservationStatus::CONFIRMED->value,
        ]);
    }

    public function test_personal_no_puede_crear_reserva_en_court_fuera_de_su_scope(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branchPermitida */
        $branchPermitida = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var Branch $branchNoPermitida */
        $branchNoPermitida = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var TipoCourt $tipoCourt */
        $tipoCourt = TipoCourt::factory()->createOne();

        /** @var Court $court */
        $court = Court::factory()->createOne([
            'branch_id' => $branchNoPermitida->id,
            'tipo_court_id' => $tipoCourt->id,
            'active' => true,
        ]);

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission('reservation.create')
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branchPermitida)
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/courts/{$court->id}/reservations",
                [
                    'guest_name' => 'Juan',
                    'guest_phone' => '111111111',
                    'starts_at' => '2030-09-10 14:00:00',
                    'ends_at' => '2030-09-10 15:00:00',
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseCount(
            'reservations',
            0
        );
    }

    public function test_cliente_autenticado_puede_reservar_para_si_mismo(): void
    {
        /** @var User $customer */
        $customer = User::factory()->createOne();

        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createCourtScenario();

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 60,
        );

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->postJson(
                "/api/courts/{$court->id}/book",
                [
                    'starts_at' => '2030-09-10 14:00:00',
                    'ends_at' => '2030-09-10 15:00:00',
                ]
            );

        $response->assertCreated();

        $this->assertDatabaseHas('reservations', [
            'court_id' => $court->id,
            'customer_user_id' => $customer->id,
            'created_by_user_id' => $customer->id,
            'status' => ReservationStatus::PENDING->value,
        ]);
    }

    public function test_guest_puede_crear_reserva_sin_autenticacion(): void
    {
        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createCourtScenario();

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 60,
        );

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
        ]);

        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan Pérez',
                'email' => 'juan@test.com',
                'starts_at' => '2030-09-10 14:00:00',
                'ends_at' => '2030-09-10 15:00:00',
            ]
        );

        $response->assertCreated();

        $this->assertDatabaseHas('reservations', [
            'court_id' => $court->id,
            'customer_user_id' => null,
            'created_by_user_id' => null,
            'guest_name' => 'Juan Pérez',
            'guest_email' => 'juan@test.com',
            'status' => ReservationStatus::PENDING->value,
        ]);
    }

    public function test_guest_debe_enviar_email_o_telefono(): void
    {
        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createCourtScenario();

        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan Pérez',
                'starts_at' => '2030-09-10 14:00:00',
                'ends_at' => '2030-09-10 15:00:00',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'contact',
            ]);
    }

    public function test_no_se_puede_reservar_court_ocupada(): void
    {
        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createCourtScenario();

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 60,
        );

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
        ]);

        Reservation::factory()
            ->for($court)
            ->confirmed()
            ->between(
                '2030-09-10 14:00:00',
                '2030-09-10 16:00:00'
            )
            ->createOne();

        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan',
                'phone' => '111111',
                'starts_at' => '2030-09-10 15:00:00',
                'ends_at' => '2030-09-10 16:00:00',
            ]
        );

        $response->assertStatus(409);
    }

    public function test_reserva_cancelada_no_bloquea_disponibilidad(): void
    {
        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createCourtScenario();

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 60,
        );

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
        ]);

        Reservation::factory()
            ->for($court)
            ->cancelled()
            ->between(
                '2030-09-10 14:00:00',
                '2030-09-10 15:00:00'
            )
            ->createOne();

        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan',
                'phone' => '111111',
                'starts_at' => '2030-09-10 14:00:00',
                'ends_at' => '2030-09-10 15:00:00',
            ]
        );

        $response->assertCreated();
    }

    public function test_no_permite_duracion_que_no_respeta_intervalo(): void
    {
        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createCourtScenario();

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 60,
        );

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
        ]);

        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan',
                'phone' => '111111',
                'starts_at' => '2030-09-10 14:00:00',
                'ends_at' => '2030-09-10 15:30:00',
            ]
        );

        $response->assertUnprocessable();
    }

    public function test_no_permite_inicio_fuera_de_la_grilla_del_intervalo(): void
    {
        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createCourtScenario();

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 60,
        );

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
        ]);

        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan',
                'phone' => '111111',
                'starts_at' => '2030-09-10 14:30:00',
                'ends_at' => '2030-09-10 15:30:00',
            ]
        );

        $response->assertUnprocessable();
    }

    public function test_personal_con_permiso_puede_cancelar_reserva(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createBaseScenario(
            permission: 'reservation.cancel'
        );

        /** @var Reservation $reservation */
        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->between(
                '2030-09-10 14:00:00',
                '2030-09-10 15:00:00'
            )
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/reservations/{$reservation->id}/cancel"
            );

        $response->assertSuccessful();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CANCELLED->value,
        ]);

        $this->assertNotNull(
            Reservation::find($reservation->id)?->cancelled_at
        );
    }

    public function test_usuario_sin_permiso_no_puede_cancelar_reserva(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createBaseScenario(
            permission: 'reservation.view'
        );

        /** @var Reservation $reservation */
        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/reservations/{$reservation->id}/cancel"
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CONFIRMED->value,
        ]);
    }

    private function createBaseScenario(
        string $permission
    ): array {
        /** @var User $user */
        $user = User::factory()->createOne();

        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createCourtScenario();

        /** @var Role $role */
        $role = Role::factory()
            ->withPermission($permission)
            ->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch)
            ->createOne();

        return [
            $user,
            $club,
            $branch,
            $court,
            $tipoCourt,
        ];
    }

    public function test_guest_no_puede_forzar_reserva_confirmada(): void
    {
        [$club, $branch, $court, $tipoCourt] = $this->createCourtScenario();

        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'guest_name' => 'Alejo',
                'guest_email' => 'alejo@test.com',
                'starts_at' => now()->addDays(2)->setTime(18, 0)->toDateTimeString(),
                'ends_at' => now()->addDays(2)->setTime(19, 0)->toDateTimeString(),
                'confirmed' => true,
            ]
        );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'confirmed',
        ]);
    }


    public function test_cliente_no_puede_forzar_reserva_confirmada(): void
    {
        [$club, $branch, $court, $tipoCourt] = $this->createCourtScenario();

        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson(
            "/api/courts/{$court->id}/book",
            [
                'starts_at' => now()->addDays(2)->setTime(18, 0)->toDateTimeString(),
                'ends_at' => now()->addDays(2)->setTime(19, 0)->toDateTimeString(),
                'confirmed' => true,
            ]
        );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'confirmed',
        ]);
    }


    public function test_detalle_de_reserva_incluye_resumen_financiero_con_multiples_pagos(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createBaseScenario(
            permission: 'reservation.view'
        );

        /**
         * Reserva total: $40.000
         */
        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        /**
         * Pagos aprobados:
         *
         * 12.000 + 8.000 = 20.000
         */
        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('12000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('8000.00')
            ->createOne();

        /**
         * Estos NO tienen que entrar en el total aprobado.
         */
        Payment::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('10000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->rejected()
            ->withAmount('5000.00')
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/reservations/{$reservation->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $reservation->id
            )
            ->assertJsonPath(
                'data.total_price',
                '40000.00'
            )
            ->assertJsonPath(
                'data.payment_summary.total_price',
                '40000.00'
            )
            ->assertJsonPath(
                'data.payment_summary.approved_amount',
                '20000.00'
            )
            ->assertJsonPath(
                'data.payment_summary.required_deposit',
                '20000.00'
            )
            ->assertJsonPath(
                'data.payment_summary.remaining_amount',
                '20000.00'
            )
            ->assertJsonPath(
                'data.payment_summary.financial_status',
                'pago_senia'
            );
    }

    public function test_admin_puede_cancelar_reserva_sin_generar_refund(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('reservation.cancel');

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
                'total_price' => '100000.00',
            ]);

        Payment::factory()
            ->approved()
            ->forReservation($reservation)
            ->withAmount('50000.00')
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/reservations/{$reservation->id}/cancel",
                [
                    'create_refund' => false,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'cancelled'
            );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseMissing('payment_refunds', [
            'reservation_id' => $reservation->id,
        ]);
    }

    public function test_admin_puede_cancelar_reserva_y_generar_refund_pendiente(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('reservation.cancel');

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
                'total_price' => '100000.00',
            ]);

        Payment::factory()
            ->approved()
            ->forReservation($reservation)
            ->withAmount('25000.00')
            ->createOne();

        Payment::factory()
            ->approved()
            ->forReservation($reservation)
            ->withAmount('25000.00')
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/reservations/{$reservation->id}/cancel",
                [
                    'create_refund' => true,
                    'refund_reason' => 'Cancha no disponible',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'cancelled'
            );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('payment_refunds', [
            'reservation_id' => $reservation->id,
            'amount' => '50000.00',
            'status' => RefundStatus::PENDING->value,
            'reason' => 'Cancha no disponible',
            'created_by_user_id' => $user->id,
            'method' => null,
            'completed_by_user_id' => null,
            'completed_at' => null,
        ]);
    }

    public function test_cancelacion_con_create_refund_no_genera_refund_si_no_hay_pagos_aprobados(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('reservation.cancel');

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        Payment::factory()
            ->pending()
            ->forReservation($reservation)
            ->withAmount('50000.00')
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/reservations/{$reservation->id}/cancel",
                [
                    'create_refund' => true,
                    'refund_reason' => 'Cancelación administrativa',
                ]
            );

        $response->assertOk();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseMissing('payment_refunds', [
            'reservation_id' => $reservation->id,
        ]);
    }


    public function test_refund_generado_por_cancelacion_registra_al_usuario_que_cancelo(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('reservation.cancel');

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        Payment::factory()
            ->approved()
            ->forReservation($reservation)
            ->withAmount('30000.00')
            ->createOne();

        $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/reservations/{$reservation->id}/cancel",
                [
                    'create_refund' => true,
                ]
            )
            ->assertOk();

        $refund = PaymentRefund::query()
            ->where('reservation_id', $reservation->id)
            ->first();

        $this->assertNotNull($refund);

        $this->assertSame(
            $user->id,
            $refund->created_by_user_id
        );
    }

    public function test_usuario_sin_permiso_no_puede_cancelar_y_generar_refund(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('reservation.view');

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        Payment::factory()
            ->approved()
            ->forReservation($reservation)
            ->withAmount('30000.00')
            ->createOne();

        $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/reservations/{$reservation->id}/cancel",
                [
                    'create_refund' => true,
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('payment_refunds', [
            'reservation_id' => $reservation->id,
        ]);

        $reservation->refresh();

        $this->assertNotSame(
            'CANCELLED',
            $reservation->status->value
        );
    }


    public function test_create_refund_debe_ser_booleano(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('reservation.cancel');

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/reservations/{$reservation->id}/cancel",
                [
                    'create_refund' => 'cualquier cosa',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'create_refund',
            ]);
    }


    public function test_guest_puede_reservar_cruzando_medianoche_si_la_sucursal_sigue_abierta(): void
    {
        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createCourtScenario();

        $branch->update([
            'opening_time' => '08:00:00',
            'closing_time' => '02:00:00',
        ]);

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 30,
        );

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
            'active' => true,
        ]);

        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan Pérez',
                'email' => 'juan@test.com',
                'starts_at' => '2030-09-10 23:30:00',
                'ends_at' => '2030-09-11 00:30:00',
            ]
        );

        $response->assertCreated();

        $this->assertDatabaseHas('reservations', [
            'court_id' => $court->id,
            'starts_at' => '2030-09-10 23:30:00',
            'ends_at' => '2030-09-11 00:30:00',
        ]);
    }

    public function test_guest_puede_reservar_despues_de_medianoche_dentro_de_la_jornada_anterior(): void
    {
        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createCourtScenario();

        $branch->update([
            'opening_time' => '08:00:00',
            'closing_time' => '02:00:00',
        ]);

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 30,
        );

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
            'active' => true,
        ]);

        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan Pérez',
                'email' => 'juan@test.com',
                'starts_at' => '2030-09-11 00:30:00',
                'ends_at' => '2030-09-11 01:30:00',
            ]
        );

        $response->assertCreated();
    }

    public function test_no_se_puede_reservar_mas_alla_del_cierre_nocturno(): void
    {
        [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ] = $this->createCourtScenario();

        $branch->update([
            'opening_time' => '08:00:00',
            'closing_time' => '02:00:00',
        ]);

        $this->createInterval(
            branchId: $branch->id,
            tipoCourtId: $tipoCourt->id,
            minutes: 30,
        );

        CourtPrice::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'price' => '25000.00',
            'active' => true,
        ]);

        $response = $this->postJson(
            "/api/public/courts/{$court->id}/book",
            [
                'name' => 'Juan Pérez',
                'email' => 'juan@test.com',
                'starts_at' => '2030-09-11 01:30:00',
                'ends_at' => '2030-09-11 02:30:00',
            ]
        );

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('reservations', [
            'court_id' => $court->id,
            'starts_at' => '2030-09-11 01:30:00',
        ]);
    }

    private function createCourtScenario(): array
    {
        /** @var Club $club */
        $club = Club::factory()->createOne();

        /** @var Branch $branch */
        $branch = Branch::factory()
            ->for($club)
            ->createOne();

        /** @var TipoCourt $tipoCourt */
        $tipoCourt = TipoCourt::factory()
            ->createOne();

        /** @var Court $court */
        $court = Court::factory()->createOne([
            'branch_id' => $branch->id,
            'tipo_court_id' => $tipoCourt->id,
            'active' => true,
        ]);

        return [
            $club,
            $branch,
            $court,
            $tipoCourt,
        ];
    }

    private function createInterval(
        int $branchId,
        int $tipoCourtId,
        int $minutes
    ): void {
        DB::table('interval_time_tipo_court')
            ->insert([
                'branch_id' => $branchId,
                'tipo_court_id' => $tipoCourtId,
                'interval_minutes' => $minutes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
