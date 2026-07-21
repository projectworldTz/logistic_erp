<?php

namespace Tests\Feature\Tenant;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function registerTenant(string $email = 'jane@acme.test', string $companyName = 'Acme Logistics'): array
    {
        $response = $this->postJson('/api/v1/tenants/register', [
            'plan_code' => 'starter',
            'owner' => ['name' => 'Jane Doe', 'email' => $email, 'password' => 'SecurePass123'],
            'company' => [
                'name' => $companyName,
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

    private function inviteAndLogin(string $ownerToken, string $role, string $email): string
    {
        $this->withHeader('Authorization', "Bearer {$ownerToken}")
            ->postJson('/api/v1/users', [
                'name' => 'Test User',
                'email' => $email,
                'roles' => [$role],
                'password' => 'RolePass123',
            ])->assertCreated();

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'RolePass123',
        ])->json('token');
    }

    public function test_cashier_can_view_and_manage_invoices_and_expenses_but_not_manage_users(): void
    {
        $registration = $this->registerTenant();
        $ownerToken = $registration['token'];

        $cashierToken = $this->inviteAndLogin($ownerToken, 'Cashier', 'cashier@acme.test');

        $this->withHeader('Authorization', "Bearer {$cashierToken}")
            ->getJson('/api/v1/finance/invoices')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$cashierToken}")
            ->getJson('/api/v1/finance/expenses')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$cashierToken}")
            ->getJson('/api/v1/finance/exchange-rates')
            ->assertOk();

        // Cashier can record income (invoices) but is not a fleet/HR administrator.
        $this->withHeader('Authorization', "Bearer {$cashierToken}")
            ->getJson('/api/v1/users')
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$cashierToken}")
            ->getJson('/api/v1/fleet/vehicles')
            ->assertForbidden();
    }

    public function test_transport_coordinator_can_manage_fleet_and_freight_but_not_finance(): void
    {
        $registration = $this->registerTenant();
        $ownerToken = $registration['token'];

        $coordinatorToken = $this->inviteAndLogin($ownerToken, 'Transport Coordinator', 'coordinator@acme.test');

        $this->withHeader('Authorization', "Bearer {$coordinatorToken}")
            ->postJson('/api/v1/fleet/vehicles', [
                'registration_number' => 'KDA 999X',
                'vehicle_type' => 'truck',
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$coordinatorToken}")
            ->getJson('/api/v1/freight/bookings')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$coordinatorToken}")
            ->getJson('/api/v1/containers/items')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$coordinatorToken}")
            ->getJson('/api/v1/shipments/items')
            ->assertOk();

        // Not a finance role.
        $this->withHeader('Authorization', "Bearer {$coordinatorToken}")
            ->getJson('/api/v1/finance/invoices')
            ->assertForbidden();
    }
}
