<?php

namespace Tests\Feature\Portal;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerMessagingTest extends TestCase
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

    public function test_message_round_trips_between_portal_and_staff(): void
    {
        $registration = $this->registerTenant();
        $staffToken = $registration['token'];
        $customerId = $this->createCustomer($staffToken, 'Shipper Co');
        $this->invitePortalUser($staffToken, $customerId, 'portal@shipperco.test');

        // Once we switch identity via Sanctum::actingAs() anywhere in this
        // test, every later identity switch (including back to staff) must
        // also go through actingAs() — mixing it with a raw bearer-token
        // call afterwards resolves to the wrong (cached) user. See project
        // memory: "actingAs() hijacks later bearer requests."
        app(TenantContext::class)->clear();
        $portalUser = User::where('email', 'portal@shipperco.test')->firstOrFail();
        $staffUser = $registration['user'];
        $staffUserModel = User::where('email', $staffUser['email'])->firstOrFail();

        Sanctum::actingAs($portalUser, ['*']);
        $this->postJson('/api/v1/portal/messages', ['body' => 'Where is my shipment?'])
            ->assertCreated()
            ->assertJsonPath('data.is_from_customer', true);

        app(TenantContext::class)->clear();
        Sanctum::actingAs($staffUserModel, ['*']);

        $this->getJson("/api/v1/crm/customers/{$customerId}/messages")
            ->assertOk()
            ->assertJsonFragment(['body' => 'Where is my shipment?']);

        $this->postJson("/api/v1/crm/customers/{$customerId}/messages", ['body' => 'It departed yesterday.'])
            ->assertCreated()
            ->assertJsonPath('data.is_from_customer', false);

        app(TenantContext::class)->clear();
        Sanctum::actingAs($portalUser, ['*']);

        $this->getJson('/api/v1/portal/messages')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_messages_are_isolated_per_customer_within_the_same_tenant(): void
    {
        $registration = $this->registerTenant();
        $staffToken = $registration['token'];
        $customerA = $this->createCustomer($staffToken, 'Shipper A');
        $customerB = $this->createCustomer($staffToken, 'Shipper B');
        $this->invitePortalUser($staffToken, $customerA, 'portal-a@test.test');
        $this->invitePortalUser($staffToken, $customerB, 'portal-b@test.test');

        app(TenantContext::class)->clear();
        $portalUserA = User::where('email', 'portal-a@test.test')->firstOrFail();
        Sanctum::actingAs($portalUserA, ['*']);
        $this->postJson('/api/v1/portal/messages', ['body' => 'Message from A'])->assertCreated();

        app(TenantContext::class)->clear();
        $portalUserB = User::where('email', 'portal-b@test.test')->firstOrFail();
        Sanctum::actingAs($portalUserB, ['*']);

        $this->getJson('/api/v1/portal/messages')->assertOk()->assertJsonCount(0, 'data');
    }
}
