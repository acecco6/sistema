<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LogoutTest extends TestCase
{
    use RefreshDatabase;

    private string $endPoint = '/api/auth/logout';

    public function test_usuario_autenticado_puede_cerrar_sesion(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        $token = $user->createToken('TestToken')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->postJson($this->endPoint);

        $response->assertSuccessful();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_usuario_no_autenticado_no_puede_cerrar_sesion(): void
    {
        $response = $this->postJson(
            $this->endPoint
        );

        $response->assertUnauthorized();
    }

    public function test_usuario_no_puede_hacer_logout_con_token_invalido(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        /*
         * No usamos Sanctum::actingAs().
         *
         * Queremos que Sanctum intente autenticar
         * realmente este Bearer token.
         */
        $response = $this
            ->withToken('invalid-token')
            ->postJson($this->endPoint);

        $response->assertUnauthorized();
    }

    public function test_usuario_no_puede_cerrar_sesion_con_token_expirado(): void
    {
        /*
         * El test no debe depender de lo que tengas
         * configurado localmente.
         */
        config([
            'sanctum.expiration' => 60,
        ]);

        /** @var User $user */
        $user = User::factory()->createOne();

        $tokenResult = $user->createToken(
            'TestToken'
        );

        $plainTextToken = $tokenResult->plainTextToken;

        DB::table('personal_access_tokens')->where('id', $tokenResult->accessToken->id)->update([
            'created_at' => Carbon::now()->subHours(2),
        ]);

        $response = $this
            ->withToken($plainTextToken)
            ->postJson($this->endPoint);

        $response->assertUnauthorized();
    }
}
