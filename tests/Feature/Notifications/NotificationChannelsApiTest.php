<?php

use App\Models\Branch;
use App\Models\Club;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('NotificationChannelsApi', function () {
    test('requiere autenticacion para consultar canales de notificaciones', function () {
        $club = Club::factory()->create();

        $response = $this->getJson("/api/clubs/{$club->id}/notification-channels");

        $response->assertStatus(401);
    });

    test('retorna canal global cuando el usuario tiene membresia global activa', function () {
        $user = User::factory()->create();
        $club = Club::factory()->create();

        Membership::factory()->global()->create([
            'user_id' => $user->id,
            'club_id' => $club->id,
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/clubs/{$club->id}/notification-channels");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'club_id' => $club->id,
                    'channels' => ["club.{$club->id}"],
                ],
            ]);
    });

    test('retorna canales de sucursales cuando el usuario tiene membresias por sucursal', function () {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        $branch1 = Branch::factory()->create(['club_id' => $club->id]);
        $branch2 = Branch::factory()->create(['club_id' => $club->id]);

        Membership::factory()->forBranch($branch1)->create([
            'user_id' => $user->id,
            'active' => true,
        ]);
        Membership::factory()->forBranch($branch2)->create([
            'user_id' => $user->id,
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/clubs/{$club->id}/notification-channels");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'club_id' => $club->id,
                    'channels' => [
                        "club.{$club->id}.branch.{$branch1->id}",
                        "club.{$club->id}.branch.{$branch2->id}",
                    ],
                ],
            ]);
    });

    test('rechaza la consulta cuando el usuario no tiene membresias activas en el club', function () {
        $user = User::factory()->create();
        $club = Club::factory()->create();

        Membership::factory()->global()->inactive()->create([
            'user_id' => $user->id,
            'club_id' => $club->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/clubs/{$club->id}/notification-channels");

        $response->assertForbidden();
    });
});
