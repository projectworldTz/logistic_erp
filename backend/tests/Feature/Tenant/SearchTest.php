<?php

namespace Tests\Feature\Tenant;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SearchTest extends TestCase
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

    public function test_owner_can_search_across_modules(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];

        $customer = Customer::create(['tenant_id' => $tenantId, 'company_name' => 'Zanzibar Spice Traders']);
        Quotation::factory()->create(['tenant_id' => $tenantId, 'customer_id' => $customer->id]);
        Invoice::factory()->create(['tenant_id' => $tenantId, 'customer_id' => $customer->id]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/search?q=Zanzibar')
            ->assertOk();

        $response->assertJsonPath('data.customers.0.label', 'Zanzibar Spice Traders');
    }

    public function test_user_only_sees_results_for_modules_they_have_permission_for(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];

        $customer = Customer::create(['tenant_id' => $tenantId, 'company_name' => 'Kilimanjaro Coffee']);
        Quotation::factory()->create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'origin_port' => 'Kilimanjaro Depot',
        ]);
        Invoice::factory()->create(['tenant_id' => $tenantId, 'customer_id' => $customer->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $driver = User::factory()->create(['tenant_id' => $tenantId]);
        $driver->assignRole('Sales Executive'); // has quotations.items.view + crm.customers.view, not finance.invoices.view

        app(TenantContext::class)->clear();
        Sanctum::actingAs($driver, ['*']);
        app(TenantContext::class)->set($tenantId);

        $response = $this->getJson('/api/v1/search?q=Kilimanjaro')->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('customers', $data);
        $this->assertArrayHasKey('quotations', $data);
        $this->assertArrayNotHasKey('invoices', $data);
    }

    public function test_empty_query_returns_empty_results(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/search?q=')
            ->assertOk();

        $this->assertEmpty($response->json('data'));
    }
}
