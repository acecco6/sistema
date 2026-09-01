<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\Enums\FinancialStatus;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Club;
use App\Models\Court;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\TipoCourt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GetReservationPaymentsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_con_permiso_puede_ver_pagos_de_una_reserva(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
        ] = $this->createBaseScenario(
            permission: 'payment.view'
        );

        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('20000.00')
            ->createOne([
                'method' => PaymentMethod::MERCADO_PAGO,
                'created_at' => now()->subMinutes(10),
            ]);

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('10000.00')
            ->createOne([
                'method' => PaymentMethod::CASH,
                'created_at' => now()->subMinutes(5),
            ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/reservations/{$reservation->id}/payments"
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.payments'
            )
            ->assertJsonPath(
                'data.payments.0.amount',
                '20000.00'
            )
            ->assertJsonPath(
                'data.payments.0.method',
                PaymentMethod::MERCADO_PAGO->value
            )
            ->assertJsonPath(
                'data.payments.0.status',
                PaymentStatus::APPROVED->value
            )
            ->assertJsonPath(
                'data.payments.1.amount',
                '10000.00'
            )
            ->assertJsonPath(
                'data.payments.1.method',
                PaymentMethod::CASH->value
            )
            ->assertJsonPath(
                'data.payment_summary.total_price',
                '40000.00'
            )
            ->assertJsonPath(
                'data.payment_summary.approved_amount',
                '30000.00'
            )
            ->assertJsonPath(
                'data.payment_summary.remaining_amount',
                '10000.00'
            );
    }

    public function test_pagos_se_devuelven_en_orden_cronologico(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
        ] = $this->createBaseScenario(
            permission: 'payment.view'
        );

        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        /*
         * Lo creamos primero, pero con fecha más nueva.
         */
        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('20000.00')
            ->withExternalReference('SEGUNDO-PAGO')
            ->createOne([
                'created_at' => now()->subMinutes(5),
            ]);

        /*
         * Lo creamos después, pero cronológicamente ocurrió antes.
         */
        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('10000.00')
            ->withExternalReference('PRIMER-PAGO')
            ->createOne([
                'created_at' => now()->subMinutes(15),
            ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/reservations/{$reservation->id}/payments"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.payments.0.external_reference',
                'PRIMER-PAGO'
            )
            ->assertJsonPath(
                'data.payments.1.external_reference',
                'SEGUNDO-PAGO'
            );
    }

    public function test_resumen_financiero_solo_suma_pagos_aprobados(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
        ] = $this->createBaseScenario(
            permission: 'payment.view'
        );

        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('20000.00')
            ->createOne();

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
                "/api/reservations/{$reservation->id}/payments"
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                3,
                'data.payments'
            )
            ->assertJsonPath(
                'data.payment_summary.approved_amount',
                '20000.00'
            )
            ->assertJsonPath(
                'data.payment_summary.remaining_amount',
                '20000.00'
            );
    }

    public function test_reserva_totalmente_pagada_devuelve_estado_paid(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
        ] = $this->createBaseScenario(
            permission: 'payment.view'
        );

        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('20000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('20000.00')
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/reservations/{$reservation->id}/payments"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.payment_summary.approved_amount',
                '40000.00'
            )
            ->assertJsonPath(
                'data.payment_summary.remaining_amount',
                '0.00'
            )
            ->assertJsonPath(
                'data.payment_summary.financial_status',
                FinancialStatus::PAID->value
            );
    }

    public function test_reserva_sin_pagos_devuelve_historial_vacio(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
        ] = $this->createBaseScenario(
            permission: 'payment.view'
        );

        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/reservations/{$reservation->id}/payments"
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.payments'
            )
            ->assertJsonPath(
                'data.payment_summary.approved_amount',
                '0.00'
            )
            ->assertJsonPath(
                'data.payment_summary.remaining_amount',
                '40000.00'
            )
            ->assertJsonPath(
                'data.payment_summary.financial_status',
                FinancialStatus::UNPAID->value
            );
    }

    public function test_usuario_sin_autenticacion_no_puede_ver_pagos(): void
    {
        $reservation = Reservation::factory()
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        $response = $this->getJson(
            "/api/reservations/{$reservation->id}/payments"
        );

        $response->assertUnauthorized();
    }

    public function test_usuario_sin_permiso_no_puede_ver_pagos(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

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

        $role = Role::factory()->createOne();

        Membership::factory()
            ->for($user)
            ->for($club)
            ->for($role)
            ->forBranch($branch)
            ->createOne();

        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/reservations/{$reservation->id}/payments"
            );

        $response->assertForbidden();
    }

    private function createBaseScenario(
        string $permission
    ): array {
        /** @var User $user */
        $user = User::factory()->createOne();

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
        ];
    }
}
