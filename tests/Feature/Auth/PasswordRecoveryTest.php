<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_solicitar_recuperacion_no_revela_si_el_email_existe_y_envia_notificacion_si_existe(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'usuario@test.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->postJson('/api/auth/forgot-password', ['email' => 'inexistente@test.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Si existe una cuenta con ese email, enviamos un enlace para recuperar la contraseña.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_restaurar_contrasena_revoca_todos_los_tokens_del_usuario(): void
    {
        $user = User::factory()->create([
            'email' => 'usuario@test.com',
            'password' => Hash::make('anterior123'),
        ]);
        $user->createToken('web');
        $user->createToken('mobile');
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'nuevaClave123',
            'confirm_password' => 'nuevaClave123',
        ])->assertOk()
            ->assertJsonPath('message', 'Contraseña actualizada. Iniciá sesión nuevamente.');

        $this->assertTrue(Hash::check('nuevaClave123', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_no_permite_restaurar_con_token_invalido(): void
    {
        $user = User::factory()->create(['email' => 'usuario@test.com']);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'token-invalido',
            'password' => 'nuevaClave123',
            'confirm_password' => 'nuevaClave123',
        ])->assertUnprocessable()
            ->assertJsonPath('status', false);
    }
}
