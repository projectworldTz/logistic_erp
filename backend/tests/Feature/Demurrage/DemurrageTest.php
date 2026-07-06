<?php

namespace Tests\Feature\Demurrage;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DemurrageTest extends TestCase
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

    private function createContainer(string $token, int $customerId, array $overrides = []): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/containers/items', array_merge([
                'customer_id' => $customerId,
                'container_number' => 'MSCU'.fake()->unique()->numerify('#######'),
                'container_type' => 'dry_40',
            ], $overrides));

        return $response->json('data.id');
    }

    private function createRateCard(string $token, array $overrides = []): array
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/demurrage/rate-cards', array_merge([
                'name' => 'Standard Import',
                'free_days' => 5,
                'currency' => 'USD',
                'is_default' => true,
                'tiers' => [
                    ['from_day' => 1, 'to_day' => 5, 'daily_rate' => 40],
                    ['from_day' => 6, 'to_day' => null, 'daily_rate' => 60],
                ],
            ], $overrides));

        return $response->json();
    }

    public function test_owner_can_create_a_rate_card_with_ordered_tiers(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $response = $this->createRateCard($token);

        $this->assertEquals('Standard Import', $response['data']['name']);
        $this->assertCount(2, $response['data']['tiers']);
        $this->assertEquals(1, $response['data']['tiers'][0]['position']);
        $this->assertEquals(40, $response['data']['tiers'][0]['daily_rate']);
        $this->assertEquals(2, $response['data']['tiers'][1]['position']);
        $this->assertNull($response['data']['tiers'][1]['to_day']);
    }

    public function test_calculate_applies_free_days_then_tiered_rates(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRateCard($token);

        // 12 days dwell, no gate-out yet (still accruing as of "now").
        $containerId = $this->createContainer($token, $customerId, [
            'gate_in_date' => now()->subDays(12)->toDateString(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/items/{$containerId}/demurrage/calculate");

        $response->assertCreated();
        $data = $response->json('data');

        $this->assertEquals(12, $data['dwell_days']);
        $this->assertEquals(5, $data['free_days']);
        $this->assertEquals(7, $data['chargeable_days']);
        // 5 days x $40 (tier 1) + 2 days x $60 (tier 2, open-ended) = 320
        $this->assertEquals(320, $data['amount']);
        $this->assertEquals('pending', $data['status']);
        $this->assertCount(2, $data['breakdown']);
    }

    public function test_container_still_within_free_days_has_zero_chargeable_days(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRateCard($token);

        $containerId = $this->createContainer($token, $customerId, [
            'gate_in_date' => now()->subDays(2)->toDateString(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/items/{$containerId}/demurrage/calculate");

        $response->assertCreated();
        $data = $response->json('data');

        $this->assertEquals(2, $data['dwell_days']);
        $this->assertEquals(0, $data['chargeable_days']);
        $this->assertEquals(0, $data['amount']);
    }

    public function test_dashboard_lists_only_containers_still_at_port_sorted_by_accrued_amount(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRateCard($token);

        $atRiskContainer = $this->createContainer($token, $customerId, [
            'container_number' => 'MSCU0000001',
            'gate_in_date' => now()->subDays(1)->toDateString(),
        ]);
        $accruingContainer = $this->createContainer($token, $customerId, [
            'container_number' => 'MSCU0000002',
            'gate_in_date' => now()->subDays(10)->toDateString(),
        ]);
        // Already gated out — should not appear on the live dashboard.
        $this->createContainer($token, $customerId, [
            'container_number' => 'MSCU0000003',
            'gate_in_date' => now()->subDays(20)->toDateString(),
            'gate_out_date' => now()->subDays(1)->toDateString(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/demurrage/dashboard');

        $response->assertOk();
        $rows = $response->json('data');

        $this->assertCount(2, $rows);
        $this->assertEquals($accruingContainer, $rows[0]['container_id']);
        $this->assertEquals('accruing', $rows[0]['risk_level']);
        $this->assertEquals($atRiskContainer, $rows[1]['container_id']);
    }

    public function test_pending_charge_can_be_waived_with_a_reason_and_then_cannot_be_invoiced(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRateCard($token);

        $containerId = $this->createContainer($token, $customerId, [
            'gate_in_date' => now()->subDays(12)->toDateString(),
        ]);

        $charge = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/items/{$containerId}/demurrage/calculate")
            ->json('data');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/demurrage/charges/{$charge['id']}/waive", ['reason' => 'Customer dispute, goodwill waiver'])
            ->assertOk()
            ->assertJsonPath('data.status', 'waived')
            ->assertJsonPath('data.waived_reason', 'Customer dispute, goodwill waiver');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/demurrage/charges/{$charge['id']}/generate-invoice")
            ->assertStatus(422);
    }

    public function test_pending_charge_can_be_invoiced_and_creates_a_linked_invoice(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRateCard($token);

        $containerId = $this->createContainer($token, $customerId, [
            'gate_in_date' => now()->subDays(12)->toDateString(),
        ]);

        $charge = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/items/{$containerId}/demurrage/calculate")
            ->json('data');

        $invoiceResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/demurrage/charges/{$charge['id']}/generate-invoice");

        $invoiceResponse->assertCreated();
        $this->assertEquals(320, $invoiceResponse->json('data.subtotal'));
        $this->assertEquals($customerId, $invoiceResponse->json('data.customer_id'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/demurrage/charges/{$charge['id']}")
            ->assertJsonPath('data.status', 'invoiced')
            ->assertJsonPath('data.invoice_id', $invoiceResponse->json('data.id'));

        // Cannot invoice the same charge twice.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/demurrage/charges/{$charge['id']}/generate-invoice")
            ->assertStatus(422);
    }

    public function test_rate_cards_and_charges_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $tokenA = $registrationA['token'];
        $customerA = $this->createCustomer($tokenA);
        $this->createRateCard($tokenA);
        $containerA = $this->createContainer($tokenA, $customerA, [
            'gate_in_date' => now()->subDays(12)->toDateString(),
        ]);
        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson("/api/v1/containers/items/{$containerA}/demurrage/calculate")
            ->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/demurrage/rate-cards')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/demurrage/charges')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/demurrage/dashboard')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_user_without_demurrage_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $driver = User::factory()->create(['tenant_id' => $tenantId]);
        $driver->assignRole('Driver');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($driver, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->getJson('/api/v1/demurrage/dashboard')->assertForbidden();
        $this->getJson('/api/v1/demurrage/rate-cards')->assertForbidden();
    }
}
