<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_reset_for_existing_email_returns_generic_message_and_sends_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'owner@example.test']);

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'owner@example.test']);

        $response->assertOk();
        $message = $response->json('message');

        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $this->assertSame($message, $this->forgotPassword('nonexistent@example.test'));
    }

    public function test_requesting_reset_for_nonexistent_email_returns_same_generic_message_and_sends_nothing(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test']);

        $response->assertOk();
        Notification::assertNothingSent();
    }

    public function test_full_reset_round_trip_changes_password_and_revokes_old_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'owner@example.test',
            'password' => Hash::make('OldPassword123'),
            'status' => 'active',
        ]);
        $oldToken = $user->createToken('api')->plainTextToken;

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'owner@example.test'])->assertOk();

        $capturedToken = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$capturedToken) {
            $capturedToken = $notification->token;

            return true;
        });

        $this->assertNotNull($capturedToken);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'owner@example.test',
            'token' => $capturedToken,
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword456', $user->password));
        $this->assertSame(0, $user->tokens()->count());

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.test',
            'password' => 'NewPassword456',
        ])->assertOk();
    }

    public function test_invalid_token_is_rejected_and_does_not_change_password(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.test',
            'password' => Hash::make('OldPassword123'),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'owner@example.test',
            'token' => 'not-a-real-token',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ]);

        $response->assertStatus(422);

        $user->refresh();
        $this->assertTrue(Hash::check('OldPassword123', $user->password));
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        User::factory()->create(['email' => 'owner@example.test']);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'owner@example.test',
            'token' => 'whatever',
            'password' => 'NewPassword456',
            'password_confirmation' => 'DoesNotMatch',
        ]);

        $response->assertStatus(422);
    }

    private function forgotPassword(string $email): string
    {
        return $this->postJson('/api/v1/auth/forgot-password', ['email' => $email])->json('message');
    }
}
