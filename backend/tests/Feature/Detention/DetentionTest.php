<?php

namespace Tests\Feature\Detention;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DetentionTest extends TestCase
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
            ->postJson('/api/v1/detention/rate-cards', array_merge([
                'name' => 'Standard Detention',
                'free_days' => 4,
                'currency' => 'USD',
                'is_default' => true,
                'tiers' => [
                    ['from_day' => 1, 'to_day' => 5, 'daily_rate' => 30],
                    ['from_day' => 6, 'to_day' => null, 'daily_rate' => 50],
                ],
            ], $overrides));

        return $response->json();
    }

    public function test_owner_can_create_a_rate_card_with_ordered_tiers(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $response = $this->createRateCard($token);

        $this->assertEquals('Standard Detention', $response['data']['name']);
        $this->assertCount(2, $response['data']['tiers']);
        $this->assertEquals(1, $response['data']['tiers'][0]['position']);
        $this->assertEquals(30, $response['data']['tiers'][0]['daily_rate']);
        $this->assertEquals(2, $response['data']['tiers'][1]['position']);
        $this->assertNull($response['data']['tiers'][1]['to_day']);
    }

    public function test_calculate_uses_gate_out_to_now_not_gate_in(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRateCard($token);

        // Gated in 30 days ago (irrelevant to detention), gated out 10 days
        // ago, not yet returned empty — 10 days out, no free-day overlap
        // with a gate-in date that far back.
        $containerId = $this->createContainer($token, $customerId, [
            'gate_in_date' => now()->subDays(30)->toDateString(),
            'gate_out_date' => now()->subDays(10)->toDateString(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/items/{$containerId}/detention/calculate");

        $response->assertCreated();
        $data = $response->json('data');

        $this->assertEquals(10, $data['detention_days']);
        $this->assertEquals(4, $data['free_days']);
        $this->assertEquals(6, $data['chargeable_days']);
        // 5 days x $30 (tier 1) + 1 day x $50 (tier 2, open-ended) = 200
        $this->assertEquals(200, $data['amount']);
        $this->assertEquals('pending', $data['status']);
    }

    public function test_container_not_yet_gated_out_has_zero_detention(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRateCard($token);

        $containerId = $this->createContainer($token, $customerId, [
            'gate_in_date' => now()->subDays(20)->toDateString(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/items/{$containerId}/detention/calculate");

        // Not gated out yet — nothing to charge, no charge row created.
        $response->assertOk();
        $this->assertNull($response->json('data'));
        $this->assertEquals('within_free_days', $response->json('reason'));
        $this->assertDatabaseCount('detention_charges', 0);
    }

    public function test_recalculating_with_no_new_time_out_of_port_does_not_duplicate_the_charge(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRateCard($token);

        $containerId = $this->createContainer($token, $customerId, [
            'gate_out_date' => now()->subDays(10)->toDateString(),
        ]);

        $first = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/items/{$containerId}/detention/calculate");
        $first->assertCreated();
        $this->assertEquals(200, $first->json('data.amount'));

        $second = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/items/{$containerId}/detention/calculate");

        $second->assertOk();
        $this->assertNull($second->json('data'));
        $this->assertEquals('no_new_charge', $second->json('reason'));
        $this->assertDatabaseCount('detention_charges', 1);
    }

    public function test_recalculating_after_more_time_out_of_port_charges_only_the_incremental_days(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRateCard($token);

        $containerId = $this->createContainer($token, $customerId, [
            'gate_out_date' => now()->subDays(10)->toDateString(),
        ]);

        $first = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/items/{$containerId}/detention/calculate");
        $first->assertCreated();
        $this->assertEquals(6, $first->json('data.chargeable_days'));
        $this->assertEquals(200, $first->json('data.amount'));

        $this->travel(2)->days();

        $second = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/items/{$containerId}/detention/calculate");
        $second->assertCreated();
        // Only the 2 new days, at tier 2's $50/day rate.
        $this->assertEquals(2, $second->json('data.chargeable_days'));
        $this->assertEquals(100, $second->json('data.amount'));

        $this->assertDatabaseCount('detention_charges', 2);
    }

    public function test_dashboard_lists_only_containers_gated_out_and_not_yet_returned_empty(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRateCard($token);

        $atRiskContainer = $this->createContainer($token, $customerId, [
            'container_number' => 'MSCU0000001',
            'gate_out_date' => now()->subDays(1)->toDateString(),
        ]);
        $accruingContainer = $this->createContainer($token, $customerId, [
            'container_number' => 'MSCU0000002',
            'gate_out_date' => now()->subDays(8)->toDateString(),
        ]);
        // Still at port (never gated out) — should not appear.
        $this->createContainer($token, $customerId, [
            'container_number' => 'MSCU0000003',
            'gate_in_date' => now()->subDays(2)->toDateString(),
        ]);
        // Already returned empty — should not appear.
        $this->createContainer($token, $customerId, [
            'container_number' => 'MSCU0000004',
            'gate_out_date' => now()->subDays(15)->toDateString(),
            'empty_return_date' => now()->subDays(1)->toDateString(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/detention/dashboard');

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
            'gate_out_date' => now()->subDays(10)->toDateString(),
        ]);

        $charge = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/items/{$containerId}/detention/calculate")
            ->json('data');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/detention/charges/{$charge['id']}/waive", ['reason' => 'Customer dispute, goodwill waiver'])
            ->assertOk()
            ->assertJsonPath('data.status', 'waived')
            ->assertJsonPath('data.waived_reason', 'Customer dispute, goodwill waiver');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/detention/charges/{$charge['id']}/generate-invoice")
            ->assertStatus(422);
    }

    public function test_pending_charge_can_be_invoiced_and_creates_a_linked_invoice(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRateCard($token);

        $containerId = $this->createContainer($token, $customerId, [
            'gate_out_date' => now()->subDays(10)->toDateString(),
        ]);

        $charge = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/items/{$containerId}/detention/calculate")
            ->json('data');

        $invoiceResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/detention/charges/{$charge['id']}/generate-invoice");

        $invoiceResponse->assertCreated();
        $this->assertEquals(200, $invoiceResponse->json('data.subtotal'));
        $this->assertEquals($customerId, $invoiceResponse->json('data.customer_id'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/detention/charges/{$charge['id']}")
            ->assertJsonPath('data.status', 'invoiced')
            ->assertJsonPath('data.invoice_id', $invoiceResponse->json('data.id'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/detention/charges/{$charge['id']}/generate-invoice")
            ->assertStatus(422);
    }

    public function test_rate_cards_and_charges_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $tokenA = $registrationA['token'];
        $customerA = $this->createCustomer($tokenA);
        $this->createRateCard($tokenA);
        $containerA = $this->createContainer($tokenA, $customerA, [
            'gate_out_date' => now()->subDays(10)->toDateString(),
        ]);
        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson("/api/v1/containers/items/{$containerA}/detention/calculate")
            ->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/detention/rate-cards')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/detention/charges')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/detention/dashboard')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_user_without_detention_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $driver = User::factory()->create(['tenant_id' => $tenantId]);
        $driver->assignRole('Driver');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($driver, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->getJson('/api/v1/detention/dashboard')->assertForbidden();
        $this->getJson('/api/v1/detention/rate-cards')->assertForbidden();
    }
}
