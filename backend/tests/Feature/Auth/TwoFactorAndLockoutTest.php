<?php

namespace Tests\Feature\Auth;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAndLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function registerTenant(string $email = 'jane@acme.test'): array
    {
        $response = $this->postJson('/api/v1/tenants/register', [
            'plan_code' => 'starter',
            'owner' => ['name' => 'Jane Doe', 'email' => $email, 'password' => 'SecurePass123'],
            'company' => [
                'name' => 'Acme Logistics',
                'country' => 'Kenya',
                'city' => 'Nairobi',
                'address' => '123 Port Rd',
                'currency' => 'USD',
                'timezone' => 'Africa/Nairobi',
                'industry' => 'Freight Forwarding',
            ],
        ]);

        return $response->json();
    }

    /**
     * A prior bearer-authenticated request pins the default auth guard to
     * Sanctum's RequestGuard, which has no attempt() method — reset it
     * before any subsequent plain (unauthenticated) login post.
     */
    private function resetAuthGuard(): void
    {
        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();
    }

    public function test_account_locks_after_five_failed_login_attempts(): void
    {
        $registration = $this->registerTenant();
        $email = $registration['user']['email'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'SecurePass123',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Too many failed attempts', $response->json('errors.email.0'));
    }

    public function test_successful_login_resets_failed_attempt_counter(): void
    {
        $registration = $this->registerTenant();
        $email = $registration['user']['email'];

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'SecurePass123',
        ])->assertOk();

        $user = \App\Models\User::where('email', $email)->first();
        $this->assertSame(0, $user->failed_login_attempts);
    }

    public function test_full_two_factor_setup_enable_and_login_challenge_flow(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $engine = new Google2FA;

        $setup = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/2fa/setup')
            ->assertOk()
            ->assertJsonStructure(['secret', 'qr_svg']);

        $secret = $setup->json('secret');
        $this->assertStringContainsString('<svg', $setup->json('qr_svg'));

        $enable = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/2fa/enable', [
                'secret' => $secret,
                'code' => $engine->getCurrentOtp($secret),
            ])
            ->assertOk()
            ->assertJsonStructure(['recovery_codes']);

        $recoveryCodes = $enable->json('recovery_codes');
        $this->assertCount(8, $recoveryCodes);

        // Logging in now must return a challenge, not a token, since 2FA is enabled.
        $this->resetAuthGuard();
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $registration['user']['email'],
            'password' => 'SecurePass123',
        ])->assertOk();

        $this->assertTrue($login->json('requires_2fa'));
        $challengeToken = $login->json('challenge_token');
        $this->assertNotEmpty($challengeToken);

        // Wrong code fails the challenge.
        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $challengeToken,
            'code' => '000000',
        ])->assertStatus(422);

        // Correct TOTP code completes login.
        $verify = $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $challengeToken,
            'code' => $engine->getCurrentOtp($secret),
        ])->assertOk();

        $this->assertNotEmpty($verify->json('token'));
    }

    public function test_recovery_code_can_complete_the_two_factor_challenge_exactly_once(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $engine = new Google2FA;

        $setup = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/2fa/setup')->json();

        $enable = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/2fa/enable', [
                'secret' => $setup['secret'],
                'code' => $engine->getCurrentOtp($setup['secret']),
            ])->json();

        $recoveryCode = $enable['recovery_codes'][0];

        $this->resetAuthGuard();
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $registration['user']['email'],
            'password' => 'SecurePass123',
        ])->json();

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $login['challenge_token'],
            'code' => $recoveryCode,
        ])->assertOk();

        // The same recovery code cannot be reused for a second login.
        $secondLogin = $this->postJson('/api/v1/auth/login', [
            'email' => $registration['user']['email'],
            'password' => 'SecurePass123',
        ])->json();

        $this->postJson('/api/v1/auth/2fa/verify', [
            'challenge_token' => $secondLogin['challenge_token'],
            'code' => $recoveryCode,
        ])->assertStatus(422);
    }

    public function test_disabling_two_factor_requires_the_correct_current_password(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $engine = new Google2FA;

        $setup = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/2fa/setup')->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/2fa/enable', [
                'secret' => $setup['secret'],
                'code' => $engine->getCurrentOtp($setup['secret']),
            ])->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/2fa/disable', ['password' => 'wrong-password'])
            ->assertStatus(422);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/2fa/disable', ['password' => 'SecurePass123'])
            ->assertOk();

        $user = \App\Models\User::where('email', $registration['user']['email'])->first();
        $this->assertNull($user->two_factor_enabled_at);
    }

    public function test_tenant_admin_can_view_login_history(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $email = $registration['user']['email'];

        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertStatus(422);

        $this->resetAuthGuard();
        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'SecurePass123',
        ])->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/login-history');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertTrue($response->json('data.0.successful'));
        $this->assertFalse($response->json('data.1.successful'));
    }
}
