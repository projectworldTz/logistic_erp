<?php

namespace Tests\Feature\Tenant;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsTest extends TestCase
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

    private function createCustomer(string $token): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', [
                'company_name' => 'Shipper Co',
                'email' => 'ops@shipperco.test',
                'status' => 'active',
            ]);

        return $response->json('data.id');
    }

    public function test_analytics_overview_returns_expected_shape(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $quotation = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/quotations/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'subtotal' => 450,
                'total_amount' => 450,
                'status' => 'accepted',
            ])->json('data');

        $shipment = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'quotation_id' => $quotation['id'],
                'direction' => 'export',
                'mode' => 'air',
                'eta' => now()->addDays(3)->toDateString(),
            ])->json('data');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/shipments/items/{$shipment['id']}/milestones", [
                'event_type' => 'departed',
                'occurred_at' => now()->subDays(2)->toDateTimeString(),
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/shipments/items/{$shipment['id']}/milestones", [
                'event_type' => 'arrived',
                'occurred_at' => now()->toDateTimeString(),
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/shipments/items/{$shipment['id']}/milestones", [
                'event_type' => 'delivered',
                'occurred_at' => now()->toDateTimeString(),
            ])->assertCreated();

        $invoice = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'shipment_id' => $shipment['id'],
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 500,
                'total_amount' => 500,
            ])->json('data');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/finance/invoices/{$invoice['id']}", ['status' => 'paid'])
            ->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/analytics/overview');

        $response->assertOk();
        $response->assertJsonStructure([
            'range' => ['from', 'to'],
            'operational' => ['avg_transit_days_by_mode', 'avg_customs_clearance_days', 'on_time_delivery_rate', 'avg_container_dwell_days', 'fleet_utilization_percent'],
            'financial' => ['revenue_by_month', 'ar_aging', 'margins'],
            'trends' => ['shipment_volume_by_month'],
            'top_customers' => ['by_revenue', 'by_volume'],
        ]);

        $this->assertEquals(2.0, $response->json('operational.avg_transit_days_by_mode.air'));
        $this->assertEquals(100.0, $response->json('operational.on_time_delivery_rate'));
        $this->assertCount(1, $response->json('financial.margins'));
        $this->assertEquals(500, $response->json('financial.margins.0.invoiced_amount'));
        $this->assertEquals(450, $response->json('financial.margins.0.quoted_amount'));
        $this->assertEquals(50, $response->json('financial.margins.0.variance'));
    }

    public function test_analytics_is_tenant_scoped(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $customerA = $this->createCustomer($registrationA['token']);

        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerA,
                'direction' => 'import',
                'mode' => 'sea',
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);

        $response = $this->getJson('/api/v1/analytics/overview');

        $response->assertOk();
        $this->assertEmpty($response->json('trends.shipment_volume_by_month'));
    }

    public function test_user_without_analytics_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/analytics/overview')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }
}
