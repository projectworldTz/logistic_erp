<?php

namespace Tests\Feature\Shipments;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelayRiskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function registerTenant(): array
    {
        $response = $this->postJson('/api/v1/tenants/register', [
            'plan_code' => 'starter',
            'owner' => ['name' => 'Jane Doe', 'email' => 'jane@acme.test', 'password' => 'SecurePass123'],
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

    private function createCustomer(string $token): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', ['company_name' => 'Shipper Co', 'status' => 'active'])
            ->json('data.id');
    }

    /**
     * Creates a completed historical shipment on the given route/mode/
     * direction, with an 'arrived' milestone either before or after its
     * ETA, to seed the delay-rate statistics.
     */
    private function createCompletedShipment(string $token, int $customerId, bool $delayed): void
    {
        $eta = now()->subDays(10)->toDateString();

        $shipmentId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
                'origin_port' => 'Mombasa',
                'destination_port' => 'Dar es Salaam',
                'eta' => $eta,
            ])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/shipments/items/{$shipmentId}", ['status' => 'arrived'])
            ->assertOk();

        $occurredAt = $delayed
            ? now()->subDays(8)->toIso8601String()
            : now()->subDays(11)->toIso8601String();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/shipments/items/{$shipmentId}/milestones", [
                'event_type' => 'arrived',
                'occurred_at' => $occurredAt,
            ])->assertCreated();
    }

    public function test_delay_risk_is_computed_from_historical_route_outcomes(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        // 2 delayed, 1 on-time on the same route => 66.7% historical delay rate.
        $this->createCompletedShipment($token, $customerId, delayed: true);
        $this->createCompletedShipment($token, $customerId, delayed: true);
        $this->createCompletedShipment($token, $customerId, delayed: false);

        $newShipmentId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
                'origin_port' => 'Mombasa',
                'destination_port' => 'Dar es Salaam',
            ])->json('data.id');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/shipments/items/{$newShipmentId}/delay-risk");

        $response->assertOk();
        $response->assertJsonPath('risk_score', 66.7);
        $response->assertJsonPath('risk_level', 'high');
        $response->assertJsonPath('sample_size', 3);
        $response->assertJsonPath('basis', 'route');
    }

    public function test_delay_risk_reports_insufficient_data_below_the_sample_threshold(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $newShipmentId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
            ])->json('data.id');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/shipments/items/{$newShipmentId}/delay-risk");

        $response->assertOk();
        $response->assertJsonPath('risk_score', null);
        $response->assertJsonPath('risk_level', 'insufficient_data');
    }

    public function test_delay_risk_is_rejected_for_a_shipment_that_already_arrived(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $shipmentId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
            ])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/shipments/items/{$shipmentId}", ['status' => 'delivered'])
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/shipments/items/{$shipmentId}/delay-risk")
            ->assertStatus(422);
    }
}
