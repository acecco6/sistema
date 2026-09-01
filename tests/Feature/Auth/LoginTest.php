<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    private string $endPoint = '/api/auth/login';

    public function test_usuario_puede_iniciar_sesion_con_credenciales_validas(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne([
            'email' => 'usuario@test.com',
            'password' => Hash::make('password123'),
            'active' => true,
        ]);

        $response = $this->postJson(
            $this->endPoint,
            [
                'email' => $user->email,
                'password' => 'password123',
            ]
        );

        $response->assertOk();
    }

    public function test_usuario_recibe_un_token_al_iniciar_sesion_correctamente(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne([
            'email' => 'usuario@test.com',
            'password' => Hash::make('password123'),
            'active' => true,
        ]);

        $response = $this->postJson(
            $this->endPoint,
            [
                'email' => $user->email,
                'password' => 'password123',
            ]
        );

        $response->assertOk();

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'tokenable_id' => $user->id,
                'tokenable_type' => User::class,
            ]
        );
    }

    public function test_usuario_no_puede_iniciar_sesion_con_password_incorrecta(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne([
            'email' => 'usuario@test.com',
            'password' => Hash::make('password123'),
            'active' => true,
        ]);

        $response = $this->postJson(
            $this->endPoint,
            [
                'email' => $user->email,
                'password' => 'incorrecta',
            ]
        );

        $response->assertUnauthorized();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_usuario_no_puede_iniciar_sesion_con_email_inexistente(): void
    {
        $response = $this->postJson(
            $this->endPoint,
            [
                'email' => 'noexiste@test.com',
                'password' => 'password123',
            ]
        );

        $response->assertUnauthorized();
    }

    public function test_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne([
            'email' => 'usuario@test.com',
            'password' => Hash::make('password123'),
            'active' => false,
        ]);

        $response = $this->postJson(
            $this->endPoint,
            [
                'email' => $user->email,
                'password' => 'password123',
            ]
        );

        $response->assertUnauthorized();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_email_es_obligatorio_para_iniciar_sesion(): void
    {
        $response = $this->postJson(
            $this->endPoint,
            [
                'password' => 'password123',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_password_es_obligatoria_para_iniciar_sesion(): void
    {
        $response = $this->postJson(
            $this->endPoint,
            [
                'email' => 'usuario@test.com',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);
    }

    public function test_email_debe_tener_un_formato_valido(): void
    {
        $response = $this->postJson(
            $this->endPoint,
            [
                'email' => 'esto-no-es-un-email',
                'password' => 'password123',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }
}
