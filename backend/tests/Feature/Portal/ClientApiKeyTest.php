<?php

namespace Tests\Feature\Portal;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientApiKeyTest extends TestCase
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

    private function createCustomer(string $staffToken, string $companyName): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/crm/customers', [
                'company_name' => $companyName,
                'email' => strtolower(str_replace(' ', '', $companyName)).'@customer.test',
                'status' => 'active',
            ]);

        return $response->json('data.id');
    }

    private function invitePortalUser(string $staffToken, int $customerId, string $email): void
    {
        $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/users', [
                'name' => 'Portal User',
                'email' => $email,
                'customer_id' => $customerId,
                'role' => 'Customer Portal User',
                'password' => 'PortalPass123',
            ])->assertCreated();
    }

    public function test_portal_user_can_generate_and_use_a_client_api_key(): void
    {
        $registration = $this->registerTenant();
        $staffToken = $registration['token'];
        $customerId = $this->createCustomer($staffToken, 'Shipper Co');
        $this->invitePortalUser($staffToken, $customerId, 'portal@shipperco.test');

        $shipment = $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
            ])->json('data');

        app(TenantContext::class)->clear();
        $portalUser = User::where('email', 'portal@shipperco.test')->firstOrFail();
        Sanctum::actingAs($portalUser, ['*']);

        $created = $this->postJson('/api/v1/portal/api-keys', ['name' => 'Accounting Sync'])
            ->assertCreated()
            ->json();

        $this->assertStringStartsWith('cak_', $created['api_key']);
        $this->assertArrayNotHasKey('key_hash', $created['data']);

        $plaintextKey = $created['api_key'];

        // Not authenticated as anyone at all — this must work purely off the key.
        app(TenantContext::class)->clear();
        auth()->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$plaintextKey}")
            ->getJson('/api/v1/client-api/shipments')
            ->assertOk()
            ->assertJsonFragment(['id' => $shipment['id']]);

        $this->withHeader('X-Api-Key', $plaintextKey)
            ->getJson("/api/v1/client-api/shipments/{$shipment['id']}")
            ->assertOk()
            ->assertJsonPath('data.id', $shipment['id']);
    }

    public function test_client_api_key_cannot_see_another_customers_shipment_in_the_same_tenant(): void
    {
        $registration = $this->registerTenant();
        $staffToken = $registration['token'];
        $customerA = $this->createCustomer($staffToken, 'Shipper A');
        $customerB = $this->createCustomer($staffToken, 'Shipper B');
        $this->invitePortalUser($staffToken, $customerA, 'portal-a@test.test');

        $shipmentB = $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerB,
                'direction' => 'import',
                'mode' => 'sea',
            ])->json('data');

        app(TenantContext::class)->clear();
        $portalUserA = User::where('email', 'portal-a@test.test')->firstOrFail();
        Sanctum::actingAs($portalUserA, ['*']);

        $plaintextKey = $this->postJson('/api/v1/portal/api-keys', ['name' => 'Key A'])
            ->assertCreated()
            ->json('api_key');

        app(TenantContext::class)->clear();
        auth()->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$plaintextKey}")
            ->getJson("/api/v1/client-api/shipments/{$shipmentB['id']}")
            ->assertNotFound();

        $this->withHeader('Authorization', "Bearer {$plaintextKey}")
            ->getJson('/api/v1/client-api/shipments')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_revoked_client_api_key_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $staffToken = $registration['token'];
        $customerId = $this->createCustomer($staffToken, 'Shipper Co');
        $this->invitePortalUser($staffToken, $customerId, 'portal@shipperco.test');

        app(TenantContext::class)->clear();
        $portalUser = User::where('email', 'portal@shipperco.test')->firstOrFail();
        Sanctum::actingAs($portalUser, ['*']);

        $created = $this->postJson('/api/v1/portal/api-keys', ['name' => 'Temp Key'])
            ->assertCreated()
            ->json();

        $this->deleteJson("/api/v1/portal/api-keys/{$created['data']['id']}")
            ->assertNoContent();

        app(TenantContext::class)->clear();
        auth()->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$created['api_key']}")
            ->getJson('/api/v1/client-api/shipments')
            ->assertUnauthorized();
    }

    public function test_client_api_rejects_a_missing_or_bogus_key(): void
    {
        $this->getJson('/api/v1/client-api/shipments')->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer not-a-real-key')
            ->getJson('/api/v1/client-api/shipments')
            ->assertUnauthorized();
    }
}
