<?php

namespace Tests\Feature\Shipments;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentTrackingTest extends TestCase
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

    private function createShipment(string $token, int $customerId): array
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
                'origin_port' => 'Nairobi',
                'destination_port' => 'Dubai',
            ]);

        return $response->json('data');
    }

    public function test_tracking_code_is_generated_and_globally_unique_shaped(): void
    {
        $registration = $this->registerTenant();
        $customerId = $this->createCustomer($registration['token']);

        $shipment = $this->createShipment($registration['token'], $customerId);

        $this->assertNotNull($shipment['tracking_code']);
        $this->assertStringStartsWith('TRK-', $shipment['tracking_code']);
    }

    public function test_user_can_add_and_view_shipment_milestones(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $shipment = $this->createShipment($token, $customerId);

        $addMilestone = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/shipments/items/{$shipment['id']}/milestones", [
                'event_type' => 'departed',
                'location' => 'Nairobi (JKIA)',
                'occurred_at' => now()->toDateTimeString(),
                'notes' => 'Left origin airport',
            ]);

        $addMilestone->assertCreated();
        $addMilestone->assertJsonPath('data.event_type', 'departed');

        $show = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/shipments/items/{$shipment['id']}");

        $show->assertOk();
        $this->assertCount(1, $show->json('data.milestones'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'tracking_event.created']);
    }

    public function test_milestones_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $customerA = $this->createCustomer($registrationA['token']);
        $shipmentA = $this->createShipment($registrationA['token'], $customerA);

        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson("/api/v1/shipments/items/{$shipmentA['id']}/milestones", [
                'event_type' => 'booked',
                'occurred_at' => now()->toDateTimeString(),
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);

        // Tenant B cannot even resolve tenant A's shipment via route model
        // binding (TenantScope excludes it), so the milestone route 404s.
        $this->getJson("/api/v1/shipments/items/{$shipmentA['id']}")->assertNotFound();
    }

    public function test_public_tracking_lookup_returns_customer_safe_timeline(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $shipment = $this->createShipment($token, $customerId);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/shipments/items/{$shipment['id']}/milestones", [
                'event_type' => 'departed',
                'location' => 'Nairobi',
                'occurred_at' => now()->toDateTimeString(),
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/shipments/items/{$shipment['id']}/milestones", [
                'event_type' => 'exception',
                'occurred_at' => now()->toDateTimeString(),
                'notes' => 'internal ops note',
                'is_customer_visible' => false,
            ])->assertCreated();

        $track = $this->getJson("/api/v1/public/track/{$shipment['tracking_code']}");

        $track->assertOk();
        $track->assertJsonPath('data.shipment_number', $shipment['shipment_number']);
        $this->assertCount(1, $track->json('data.milestones'));
        $this->assertArrayNotHasKey('customer_id', $track->json('data'));
    }

    public function test_public_tracking_lookup_404s_for_unknown_code(): void
    {
        $this->getJson('/api/v1/public/track/TRK-DOESNOTEXIST')->assertNotFound();
    }

    public function test_public_tracking_lookup_does_not_leak_across_tenants(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $customerA = $this->createCustomer($registrationA['token']);
        $shipmentA = $this->createShipment($registrationA['token'], $customerA);

        $registrationB = $this->registerTenant('bob@globex.test', 'Globex Freight');
        $customerB = $this->createCustomer($registrationB['token']);
        $shipmentB = $this->createShipment($registrationB['token'], $customerB);

        $this->assertNotEquals($shipmentA['tracking_code'], $shipmentB['tracking_code']);

        $track = $this->getJson("/api/v1/public/track/{$shipmentA['tracking_code']}");
        $track->assertOk();
        $track->assertJsonPath('data.shipment_number', $shipmentA['shipment_number']);
    }
}
