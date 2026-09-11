<?php

use App\Models\Branch;
use App\Models\Club;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
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

        // routes/channels.php se carga al iniciar la aplicación. En testing, en ese
        // momento el broadcaster configurado en phpunit.xml es "null"; por eso los
        // callbacks quedan registrados en esa instancia. Al cambiar a Reverb para
        // probar /broadcasting/auth hay que crear el driver y registrar allí los
        // mismos callbacks de producción, sin volver a registrar la ruta HTTP.
        Broadcast::forgetDrivers();
        require base_path('routes/channel_definitions.php');
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

    test('usuario con membresia global puede autorizar canales de sucursales del club', function () {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        $branch = Branch::factory()->create(['club_id' => $club->id]);

        Membership::factory()->global()->create([
            'user_id' => $user->id,
            'club_id' => $club->id,
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-club.{$club->id}.branch.{$branch->id}",
            'socket_id' => '1234.5678',
        ])->assertOk();
    });

    test('usuario de otro club no puede autorizar el canal solicitado', function () {
        $user = User::factory()->create();
        $authorizedClub = Club::factory()->create();
        $otherClub = Club::factory()->create();

        Membership::factory()->global()->create([
            'user_id' => $user->id,
            'club_id' => $authorizedClub->id,
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-club.{$otherClub->id}",
            'socket_id' => '1234.5678',
        ])->assertForbidden();
    });
});
