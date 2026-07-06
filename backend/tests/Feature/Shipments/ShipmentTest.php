<?php

namespace Tests\Feature\Shipments;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentTest extends TestCase
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

    private function createQuotation(string $token, int $customerId): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/quotations/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'subtotal' => 1000,
                'total_amount' => 1000,
                'status' => 'accepted',
            ]);

        return $response->json('data.id');
    }

    public function test_tenant_user_can_create_list_and_update_a_shipment(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $quotationId = $this->createQuotation($token, $customerId);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'quotation_id' => $quotationId,
                'direction' => 'export',
                'mode' => 'air',
                'origin_port' => 'Nairobi',
                'destination_port' => 'Dubai',
            ]);

        $create->assertCreated();
        $shipmentId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'booked');
        $create->assertJsonPath('data.quotation_id', $quotationId);
        $this->assertNotNull($create->json('data.shipment_number'));
        $this->assertStringStartsWith('SHP-', $create->json('data.shipment_number'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/shipments/items')
            ->assertOk()
            ->assertJsonFragment(['id' => $shipmentId]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/shipments/items/{$shipmentId}", ['status' => 'in_transit'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_transit');

        $this->assertDatabaseHas('audit_logs', ['action' => 'shipment.created']);
    }

    public function test_shipment_without_quotation_is_allowed(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
            ])
            ->assertCreated()
            ->assertJsonPath('data.quotation_id', null);
    }

    public function test_shipments_are_isolated_per_tenant(): void
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

        $this->getJson('/api/v1/shipments/items')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_without_shipments_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/shipments/items')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }
}
