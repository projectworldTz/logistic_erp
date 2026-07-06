<?php

namespace Tests\Feature\Containers;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContainerTest extends TestCase
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

    public function test_tenant_user_can_create_list_and_update_a_container(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/containers/items', [
                'customer_id' => $customerId,
                'container_number' => 'MSCU1234567',
                'container_type' => 'dry_40',
            ]);

        $create->assertCreated();
        $containerId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'at_port');
        $create->assertJsonPath('data.container_number', 'MSCU1234567');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/containers/items')
            ->assertOk()
            ->assertJsonFragment(['id' => $containerId]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/containers/items/{$containerId}", ['status' => 'at_warehouse'])
            ->assertOk()
            ->assertJsonPath('data.status', 'at_warehouse');

        $this->assertDatabaseHas('audit_logs', ['action' => 'container.created']);
    }

    public function test_duplicate_container_number_within_tenant_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/containers/items', [
                'customer_id' => $customerId,
                'container_number' => 'MSCU1234567',
                'container_type' => 'dry_40',
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/containers/items', [
                'customer_id' => $customerId,
                'container_number' => 'MSCU1234567',
                'container_type' => 'dry_20',
            ])->assertUnprocessable();
    }

    public function test_containers_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $customerA = $this->createCustomer($registrationA['token']);

        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/containers/items', [
                'customer_id' => $customerA,
                'container_number' => 'MSCU1234567',
                'container_type' => 'dry_40',
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/containers/items')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_without_containers_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/containers/items')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }
}
