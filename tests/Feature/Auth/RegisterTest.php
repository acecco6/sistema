<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private string $endPoint = '/api/auth/register';
    public function test_usuario_puede_registrarse_con_datos_validos(): void
    {
        $response = $this->postJson($this->endPoint, [
            'name' => 'Alejo',
            'email' => 'alejo@test.com',
            'password' => 'password123',
            'confirm_password' => 'password123',
        ]);

        $response->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'alejo@test.com',
        ]);
    }

    public function test_usuario_no_puede_registrarse_con_diferentes_passwords(): void
    {
        $response = $this->postJson($this->endPoint, [
            'name' => 'Alejo',
            'email' => 'alejo@test.com',
            'password' => 'password123',
            'confirm_password' => '123password',
        ]);

        $response->assertUnprocessable();
    }

    public function test_usuario_no_puede_registrarse_con_nombre_nulo(): void
    {
        $response = $this->postJson($this->endPoint, [
            'name' => '',
            'email' => 'alejo@test.com',
            'password' => 'password123',
            'confirm_password' => '123password',
        ]);

        $response->assertUnprocessable();
    }

    public function test_usuario_no_puede_registrarse_con_nombre_en_numeros(): void
    {
        $response = $this->postJson($this->endPoint, [
            'name' => '123456',
            'email' => 'alejo@test.com',
            'password' => 'password123',
            'confirm_password' => '123password',
        ]);

        $response->assertUnprocessable();
    }

    public function test_usuario_no_puede_registrarse_con_password_invalido(): void
    {
        $response = $this->postJson($this->endPoint, [
            'name' => 'Alejo',
            'email' => 'alejo@test.com',
            'password' => 'pass',
            'confirm_password' => 'pass',
        ]);

        $response->assertUnprocessable();
    }

    public function test_usuario_no_puede_registrarse_con_password_nulo(): void
    {
        $response = $this->postJson($this->endPoint, [
            'name' => 'Alejo',
            'email' => 'alejo@test.com',
            'password' => null,
            'confirm_password' => 'password123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_usuario_no_puede_registrarse_con_password_confirmacion_nulo(): void
    {
        $response = $this->postJson($this->endPoint, [
            'name' => 'Alejo',
            'email' => 'alejo@test.com',
            'password' => 'password123',
            'confirm_password' => null,
        ]);

        $response->assertUnprocessable();
    }

    public function test_usuario_no_puede_registrarse_con_email_nulo(): void
    {
        $response = $this->postJson($this->endPoint, [
            'name' => 'Alejo',
            'email' => null,
            'password' => 'password123',
            'confirm_password' => 'password123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_usuario_no_puede_registrarse_con_email_invalido(): void
    {
        $response = $this->postJson($this->endPoint, [
            'name' => 'Alejo',
            'email' => 'alejotest.com',
            'password' => 'password123',
            'confirm_password' => 'password123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_usuario_no_puede_registrarse_con_email_duplicado(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        $response = $this->postJson($this->endPoint, [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'password123',
            'confirm_password' => 'password123',
        ]);

        $response->assertUnprocessable();
    }
}
