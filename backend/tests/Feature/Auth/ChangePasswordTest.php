<?php

namespace Tests\Feature\Auth;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
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

    public function test_a_staff_user_can_change_their_own_password(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'SecurePass123',
                'password' => 'NewSecurePass456',
                'password_confirmation' => 'NewSecurePass456',
            ])
            ->assertOk();

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@acme.test',
            'password' => 'NewSecurePass456',
        ])->assertOk();
    }

    public function test_a_customer_portal_user_can_change_their_own_password(): void
    {
        $registration = $this->registerTenant();
        $staffToken = $registration['token'];

        $customerId = $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/crm/customers', [
                'company_name' => 'Shipper Co',
                'email' => 'ops@shipperco.test',
                'status' => 'active',
            ])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/users', [
                'name' => 'Portal User',
                'email' => 'portal@shipperco.test',
                'customer_id' => $customerId,
                'roles' => ['Customer Portal User'],
                'password' => 'PortalPass123',
            ])->assertCreated();

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        $portalToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'portal@shipperco.test',
            'password' => 'PortalPass123',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$portalToken}")
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'PortalPass123',
                'password' => 'NewPortalPass456',
                'password_confirmation' => 'NewPortalPass456',
            ])
            ->assertOk();

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'portal@shipperco.test',
            'password' => 'NewPortalPass456',
        ])->assertOk();
    }

    public function test_change_password_rejects_an_incorrect_current_password(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'WrongPassword',
                'password' => 'NewSecurePass456',
                'password_confirmation' => 'NewSecurePass456',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_change_password_requires_confirmation_to_match(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'SecurePass123',
                'password' => 'NewSecurePass456',
                'password_confirmation' => 'DoesNotMatch',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }
}
