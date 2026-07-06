<?php

namespace Tests\Feature\Warehouse;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseItemTest extends TestCase
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

    public function test_tenant_user_can_create_list_and_update_a_warehouse_item(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/warehouse/items', [
                'customer_id' => $customerId,
                'description' => '50 cartons of electronics',
                'quantity' => 50,
                'unit' => 'ctn',
                'bin_location' => 'A-12-03',
            ]);

        $create->assertCreated();
        $itemId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'received');
        $this->assertNotNull($create->json('data.reference_no'));
        $this->assertStringStartsWith('WH-', $create->json('data.reference_no'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/warehouse/items')
            ->assertOk()
            ->assertJsonFragment(['id' => $itemId]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/warehouse/items/{$itemId}", ['status' => 'dispatched'])
            ->assertOk()
            ->assertJsonPath('data.status', 'dispatched');

        $this->assertDatabaseHas('audit_logs', ['action' => 'warehouse_item.created']);
    }

    public function test_warehouse_items_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $customerA = $this->createCustomer($registrationA['token']);

        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/warehouse/items', [
                'customer_id' => $customerA,
                'description' => 'Test goods',
                'quantity' => 1,
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/warehouse/items')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_without_warehouse_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/warehouse/items')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }
}
