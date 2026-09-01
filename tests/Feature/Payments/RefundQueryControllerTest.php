<?php

namespace Tests\Feature\Payments;

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

final class RefundQueryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_con_permiso_puede_ver_un_refund(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('refund.view');

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        $refund = PaymentRefund::factory()
            ->forReservation($reservation)
            ->pending()
            ->withAmount('25000.00')
            ->createOne([
                'reason' => 'Cancelación administrativa',
            ]);

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/refunds/{$refund->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $refund->id
            )
            ->assertJsonPath(
                'data.reservation_id',
                $reservation->id
            )
            ->assertJsonPath(
                'data.amount',
                '25000.00'
            )
            ->assertJsonPath(
                'data.status',
                RefundStatus::PENDING->value
            )
            ->assertJsonPath(
                'data.reason',
                'Cancelación administrativa'
            );
    }

    public function test_usuario_sin_permiso_no_puede_ver_un_refund(): void
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
            ->getJson(
                "/api/refunds/{$refund->id}"
            )
            ->assertForbidden();
    }

    public function test_usuario_de_otra_branch_no_puede_ver_refund(): void
    {
        [$user, $club, $branchA] =
            $this->createBaseScenario('refund.view');

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
            ->getJson(
                "/api/refunds/{$refund->id}"
            )
            ->assertForbidden();
    }

    public function test_lista_refunds_de_una_branch(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('refund.view');

        $reservation1 = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        $reservation2 = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        $refund1 = PaymentRefund::factory()
            ->forReservation($reservation1)
            ->pending()
            ->createOne();

        $refund2 = PaymentRefund::factory()
            ->forReservation($reservation2)
            ->completed()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/branches/{$branch->id}/refunds"
            );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = collect(
            $response->json('data')
        )->pluck('id');

        $this->assertTrue(
            $ids->contains($refund1->id)
        );

        $this->assertTrue(
            $ids->contains($refund2->id)
        );
    }

    public function test_puede_filtrar_refunds_pendientes(): void
    {
        [$user, $club, $branch, $court] =
            $this->createBaseScenario('refund.view');

        $reservation1 = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        $reservation2 = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $court->id,
            ]);

        $pending = PaymentRefund::factory()
            ->forReservation($reservation1)
            ->pending()
            ->createOne();

        PaymentRefund::factory()
            ->forReservation($reservation2)
            ->completed()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/branches/{$branch->id}/refunds?status=PENDING"
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $pending->id
            )
            ->assertJsonPath(
                'data.0.status',
                RefundStatus::PENDING->value
            );
    }

    public function test_no_lista_refunds_de_otra_branch(): void
    {
        [$user, $club, $branchA, $courtA] =
            $this->createBaseScenario('refund.view');

        $reservationA = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $courtA->id,
            ]);

        $refundA = PaymentRefund::factory()
            ->forReservation($reservationA)
            ->pending()
            ->createOne();

        $branchB = Branch::factory()
            ->for($club)
            ->createOne();

        $courtB = Court::factory()
            ->for($branchB)
            ->createOne();

        $reservationB = Reservation::factory()
            ->confirmed()
            ->createOne([
                'court_id' => $courtB->id,
            ]);

        $refundB = PaymentRefund::factory()
            ->forReservation($reservationB)
            ->pending()
            ->createOne();

        $response = $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/branches/{$branchA->id}/refunds"
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $refundA->id
            );

        $ids = collect(
            $response->json('data')
        )->pluck('id');

        $this->assertFalse(
            $ids->contains($refundB->id)
        );
    }

    public function test_usuario_no_puede_listar_refunds_de_otra_branch(): void
    {
        [$user, $club] =
            $this->createBaseScenario('refund.view');

        $branchB = Branch::factory()
            ->for($club)
            ->createOne();

        $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/branches/{$branchB->id}/refunds"
            )
            ->assertForbidden();
    }

    public function test_status_invalido_retorna_error_de_validacion(): void
    {
        [$user, $club, $branch] =
            $this->createBaseScenario('refund.view');

        $this
            ->actingAs($user, 'sanctum')
            ->getJson(
                "/api/branches/{$branch->id}/refunds?status=INVALID"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    public function test_usuario_no_autenticado_no_puede_ver_refunds(): void
    {
        $refund = PaymentRefund::factory()
            ->pending()
            ->createOne();

        $this
            ->getJson(
                "/api/refunds/{$refund->id}"
            )
            ->assertUnauthorized();
    }

    private function createBaseScenario(
        string $permission
    ): array {
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
