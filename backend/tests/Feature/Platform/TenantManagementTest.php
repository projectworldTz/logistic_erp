<?php

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
        $this->seed(SuperAdminSeeder::class);
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

    private function superAdminToken(): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => env('SUPER_ADMIN_EMAIL', 'admin@example.com'),
            'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
        ]);

        return $response->json('token');
    }

    public function test_super_admin_can_list_and_suspend_tenants(): void
    {
        $this->registerTenant();
        $tenant = Tenant::firstOrFail();
        $adminToken = $this->superAdminToken();

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/v1/platform/tenants')
            ->assertOk()
            ->assertJsonFragment(['id' => $tenant->id]);

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson("/api/v1/platform/tenants/{$tenant->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.suspended', 'tenant_id' => $tenant->id]);
    }

    public function test_tenant_user_cannot_access_platform_routes(): void
    {
        $registration = $this->registerTenant();
        $tenantToken = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$tenantToken}")
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }

    public function test_tenant_a_only_sees_its_own_company_and_users(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $this->registerTenant('bob@globex.test', 'Globex Freight');

        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->getJson('/api/v1/company')
            ->assertOk()
            ->assertJsonPath('data.name', 'Acme Logistics');

        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'jane@acme.test');
    }

    public function test_tenant_b_only_sees_its_own_company(): void
    {
        $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $registrationB = $this->registerTenant('bob@globex.test', 'Globex Freight');

        $this->withHeader('Authorization', "Bearer {$registrationB['token']}")
            ->getJson('/api/v1/company')
            ->assertOk()
            ->assertJsonPath('data.name', 'Globex Freight');
    }
}
