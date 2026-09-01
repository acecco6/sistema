<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\RefundStatus;
use App\Models\Branch;
use App\Models\Club;
use App\Models\Court;
use App\Models\Membership;
use App\Models\PaymentRefund;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompleteRefundControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_con_permiso_puede_completar_un_refund(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('refund.complete');

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        $refund = PaymentRefund::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('25000.00')
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/refunds/{$refund->id}/complete",
                [
                    'method' => PaymentMethod::TRANSFER->value,
                    'notes' => 'Transferencia realizada',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $refund->id
            )
            ->assertJsonPath(
                'data.status',
                RefundStatus::COMPLETED->value
            )
            ->assertJsonPath(
                'data.method',
                PaymentMethod::TRANSFER->value
            )
            ->assertJsonPath(
                'data.completed_by_user_id',
                $user->id
            );

        $this->assertDatabaseHas('payment_refunds', [
            'id' => $refund->id,
            'status' => RefundStatus::COMPLETED->value,
            'method' => PaymentMethod::TRANSFER->value,
            'completed_by_user_id' => $user->id,
            'notes' => 'Transferencia realizada',
        ]);
    }

    public function test_usuario_sin_permiso_no_puede_completar_refund(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('reservation.view');

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        $refund = PaymentRefund::factory()
            ->forReservation($reservation)
            ->pending()
            ->createOne();

        $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/refunds/{$refund->id}/complete",
                [
                    'method' => PaymentMethod::TRANSFER->value,
                ]
            )
            ->assertForbidden();

        $refund->refresh();

        $this->assertSame(
            RefundStatus::PENDING,
            $refund->status
        );
    }

    public function test_usuario_de_otra_branch_no_puede_completar_refund(): void
    {
        [$user, $club, $branchA] =
            $this->createBaseScenario('refund.complete');

        $branchB = Branch::factory()
            ->for($club)
            ->createOne();

        $courtB = Court::factory()
            ->for($branchB)
            ->createOne();

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $courtB->id,
            ]);

        $refund = PaymentRefund::factory()
            ->forReservation($reservation)
            ->pending()
            ->createOne();

        $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/refunds/{$refund->id}/complete",
                [
                    'method' => PaymentMethod::TRANSFER->value,
                ]
            )
            ->assertForbidden();

        $refund->refresh();

        $this->assertSame(
            RefundStatus::PENDING,
            $refund->status
        );
    }

    public function test_no_permite_completar_un_refund_ya_completado(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('refund.complete');

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        $refund = PaymentRefund::factory()
            ->forReservation($reservation)
            ->completed()
            ->createOne();

        $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/refunds/{$refund->id}/complete",
                [
                    'method' => PaymentMethod::CASH->value,
                ]
            )
            ->assertUnprocessable();
    }

    public function test_method_es_obligatorio(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('refund.complete');

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        $refund = PaymentRefund::factory()
            ->forReservation($reservation)
            ->pending()
            ->createOne();

        $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/refunds/{$refund->id}/complete",
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'method',
            ]);
    }

    public function test_method_debe_ser_un_payment_method_valido(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('refund.complete');

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        $refund = PaymentRefund::factory()
            ->forReservation($reservation)
            ->pending()
            ->createOne();

        $this
            ->actingAs($user, 'sanctum')
            ->patchJson(
                "/api/refunds/{$refund->id}/complete",
                [
                    'method' => 'BITCOIN',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'method',
            ]);
    }

    public function test_usuario_no_autenticado_no_puede_completar_refund(): void
    {
        $refund = PaymentRefund::factory()
            ->pending()
            ->createOne();

        $this
            ->patchJson(
                "/api/refunds/{$refund->id}/complete",
                [
                    'method' => PaymentMethod::TRANSFER->value,
                ]
            )
            ->assertUnauthorized();
    }


    private function createBaseScenario(string $permission): array
    {
        $user = User::factory()->createOne();

        $club = Club::factory()->createOne();

        $branch = Branch::factory()
            ->for($club)
            ->createOne();

        $court = Court::factory()
            ->for($branch)
            ->createOne();

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
