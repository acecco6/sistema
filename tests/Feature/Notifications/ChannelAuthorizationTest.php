<?php

use App\Models\Branch;
use App\Models\Club;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('ChannelAuthorization', function () {
    beforeEach(function () {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'local-key',
            'broadcasting.connections.reverb.secret' => 'local-secret',
            'broadcasting.connections.reverb.app_id' => 'local-app',
        ]);
    });

    test('usuario con membresia global activa puede autorizar el canal de club', function () {
        $user = User::factory()->create();
        $club = Club::factory()->create();

        Membership::factory()->create([
            'user_id' => $user->id,
            'club_id' => $club->id,
            'branch_id' => null,
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-club.' . $club->id,
            'socket_id' => '1234.5678',
        ]);

        dd($response->json(), $response->status());

        $response->assertStatus(200);
    });

    test('usuario con membresia por sucursal no puede autorizar canal global del club', function () {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        $branch = Branch::factory()->create(['club_id' => $club->id]);

        Membership::factory()->create([
            'user_id' => $user->id,
            'club_id' => $club->id,
            'branch_id' => $branch->id,
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-club.' . $club->id,
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(403);
    });

    test('usuario con membresia de sucursal autoriza su sucursal pero no otra sucursal', function () {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        $branch6 = Branch::factory()->create(['club_id' => $club->id]);
        $branch8 = Branch::factory()->create(['club_id' => $club->id]);

        Membership::factory()->create([
            'user_id' => $user->id,
            'club_id' => $club->id,
            'branch_id' => $branch6->id,
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $responseBranch6 = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-club.{$club->id}.branch.{$branch6->id}",
            'socket_id' => '1234.5678',
        ]);
        $responseBranch6->assertStatus(200);

        $responseBranch8 = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-club.{$club->id}.branch.{$branch8->id}",
            'socket_id' => '1234.5678',
        ]);
        $responseBranch8->assertStatus(403);
    });

    test('usuario con membresia inactiva no puede autorizar canales', function () {
        $user = User::factory()->create();
        $club = Club::factory()->create();

        Membership::factory()->create([
            'user_id' => $user->id,
            'club_id' => $club->id,
            'branch_id' => null,
            'active' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-club.' . $club->id,
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(403);
    });
});
