<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function registerTenant(string $email, string $companyName): array
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

    private function createCustomer(string $token, string $companyName): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', [
                'company_name' => $companyName,
                'email' => strtolower(str_replace(' ', '', $companyName)).'@customer.test',
                'status' => 'active',
            ]);

        return $response->json('data.id');
    }

    public function test_owner_can_export_customers_as_csv(): void
    {
        $registration = $this->registerTenant('owner@acme.test', 'Acme Logistics');
        $token = $registration['token'];
        $this->createCustomer($token, 'Shipper Co');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/reports/export/customers?format=csv');

        $response->assertOk();
        $this->assertStringContainsString('customers-', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Shipper Co', $response->streamedContent());
    }

    public function test_owner_can_export_customers_as_xlsx(): void
    {
        $registration = $this->registerTenant('owner@acme.test', 'Acme Logistics');
        $token = $registration['token'];
        $this->createCustomer($token, 'Shipper Co');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/reports/export/customers?format=xlsx');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_is_scoped_to_the_requesting_tenant(): void
    {
        $regA = $this->registerTenant('owner-a@acme.test', 'Acme Logistics');
        $this->createCustomer($regA['token'], 'Acme Customer');

        // Switching to a second tenant's user within one test needs Sanctum::actingAs()
        // (not a second bearer-token header) — Laravel's auth guard caches the first
        // resolved user for the life of the test process, so a bearer-token swap alone
        // silently keeps acting as tenant A. See WarehouseItemTest for the same pattern.
        $this->registerTenant('owner-b@other.test', 'Other Co');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'owner-b@other.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);
        $this->postJson('/api/v1/crm/customers', [
            'company_name' => 'Other Customer',
            'email' => 'othercustomer@customer.test',
            'status' => 'active',
        ])->assertCreated();

        app(TenantContext::class)->clear();
        $userA = User::where('email', 'owner-a@acme.test')->firstOrFail();
        Sanctum::actingAs($userA, ['*']);

        $response = $this->get('/api/v1/reports/export/customers?format=csv');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Acme Customer', $content);
        $this->assertStringNotContainsString('Other Customer', $content);
    }

    public function test_unknown_module_returns_404(): void
    {
        $registration = $this->registerTenant('owner@acme.test', 'Acme Logistics');

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->get('/api/v1/reports/export/not-a-real-module?format=csv')
            ->assertNotFound();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant('owner@acme.test', 'Acme Logistics');
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'No Access',
                'email' => 'noaccess@acme.test',
                'role' => 'Driver',
                'password' => 'SecurePass123',
            ])->assertCreated();

        app(TenantContext::class)->clear();
        $restrictedUser = User::where('email', 'noaccess@acme.test')->firstOrFail();
        Sanctum::actingAs($restrictedUser, ['*']);

        $this->get('/api/v1/reports/export/customers?format=csv')
            ->assertForbidden();
    }
}
