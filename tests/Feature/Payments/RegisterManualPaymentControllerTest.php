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

final class RegisterManualPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_con_permiso_puede_registrar_pago_manual(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
        ] = $this->createBaseScenario(
            permission: 'payment.create'
        );

        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/reservations/{$reservation->id}/payments",
                [
                    'amount' => '20000.00',
                    'method' => PaymentMethod::CASH->value,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.payment_summary.approved_amount',
                '20000.00'
            )
            ->assertJsonPath(
                'data.payment_summary.remaining_amount',
                '20000.00'
            )
            ->assertJsonPath(
                'data.payment_summary.financial_status',
                FinancialStatus::DEPOSIT_PAID->value
            );

        $this->assertDatabaseHas('payments', [
            'reservation_id' => $reservation->id,
            'amount' => '20000.00',
            'method' => PaymentMethod::CASH->value,
            'status' => PaymentStatus::APPROVED->value,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function test_pago_manual_completa_el_saldo_y_deja_reserva_paid(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
        ] = $this->createBaseScenario(
            permission: 'payment.create'
        );

        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        /*
         * Seña previa por Mercado Pago.
         */
        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('20000.00')
            ->createOne();

        /*
         * El staff cobra el saldo en efectivo.
         */
        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/reservations/{$reservation->id}/payments",
                [
                    'amount' => '20000.00',
                    'method' => PaymentMethod::CASH->value,
                ]
            );

        $response
            ->assertCreated()
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

        $this->assertDatabaseCount(
            'payments',
            2
        );
    }

    public function test_usuario_sin_autenticacion_no_puede_registrar_pago(): void
    {
        $reservation = Reservation::factory()
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        $response = $this->postJson(
            "/api/reservations/{$reservation->id}/payments",
            [
                'amount' => '10000.00',
                'method' => PaymentMethod::CASH->value,
            ]
        );

        $response->assertUnauthorized();

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_usuario_sin_permiso_no_puede_registrar_pago(): void
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

        /*
         * Rol sin payment.create.
         */
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
            ->postJson(
                "/api/reservations/{$reservation->id}/payments",
                [
                    'amount' => '10000.00',
                    'method' => PaymentMethod::CASH->value,
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_no_permite_monto_cero(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
        ] = $this->createBaseScenario(
            permission: 'payment.create'
        );

        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/reservations/{$reservation->id}/payments",
                [
                    'amount' => '0.00',
                    'method' => PaymentMethod::CASH->value,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'amount',
            ]);

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_no_permite_metodo_inexistente(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
        ] = $this->createBaseScenario(
            permission: 'payment.create'
        );

        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/reservations/{$reservation->id}/payments",
                [
                    'amount' => '10000.00',
                    'method' => 'bitcoin',
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'method',
            ]);

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_no_permite_mercado_pago_como_pago_manual(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
        ] = $this->createBaseScenario(
            permission: 'payment.create'
        );

        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/reservations/{$reservation->id}/payments",
                [
                    'amount' => '10000.00',
                    'method' => PaymentMethod::MERCADO_PAGO->value,
                ]
            );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_no_permite_pago_mayor_al_saldo_pendiente(): void
    {
        [
            $user,
            $club,
            $branch,
            $court,
        ] = $this->createBaseScenario(
            permission: 'payment.create'
        );

        $reservation = Reservation::factory()
            ->for($court)
            ->confirmed()
            ->withTotalPrice('40000.00')
            ->createOne();

        Payment::factory()
            ->forReservation($reservation)
            ->approved()
            ->withAmount('30000.00')
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/reservations/{$reservation->id}/payments",
                [
                    'amount' => '15000.00',
                    'method' => PaymentMethod::TRANSFER->value,
                ]
            );

        $response->assertUnprocessable();

        /*
         * Solo debe seguir existiendo el pago previo.
         */
        $this->assertDatabaseCount(
            'payments',
            1
        );
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
