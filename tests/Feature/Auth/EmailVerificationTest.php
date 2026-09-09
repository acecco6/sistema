<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Customer;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_verification_email(): void
    {
        Notification::fake();
        $this->postJson('/api/auth/register', ['name' => 'Cliente', 'email' => 'cliente@test.com', 'password' => 'password123', 'confirm_password' => 'password123'])->assertCreated();
        Notification::assertSentTo(User::whereEmail('cliente@test.com')->firstOrFail(), VerifyEmail::class);
    }

    public function test_unverified_user_cannot_login(): void
    {
        $user = User::factory()->unverified()->create(['password' => Hash::make('password123')]);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_signed_link_verifies_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.email_verified', true);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_links_existing_customer_without_account_by_email(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'cliente@ejemplo.com']);
        $customer = Customer::query()->create([
            'name' => 'Cliente sin cuenta', 'email' => 'cliente@ejemplo.com',
            'email_normalized' => 'cliente@ejemplo.com', 'active' => true,
        ]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->getJson($url)->assertOk();

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'user_id' => $user->id]);
    }

    public function test_authenticated_user_can_resend_verification_email(): void
    {
        Notification::fake(); $user = User::factory()->unverified()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/auth/email/verification-notification')->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
