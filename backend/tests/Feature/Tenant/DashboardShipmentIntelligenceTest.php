<?php

namespace Tests\Feature\Tenant;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardShipmentIntelligenceTest extends TestCase
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

    private function createCustomer(string $token, string $suffix): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', [
                'company_name' => "Shipper {$suffix}",
                'email' => "ops{$suffix}@shipperco.test",
                'status' => 'active',
            ])->json('data.id');
    }

    private function createShipment(string $token, int $customerId, ?string $eta, string $status = 'booked'): int
    {
        $id = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
                'eta' => $eta,
            ])->json('data.id');

        if ($status !== 'booked') {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->putJson("/api/v1/shipments/items/{$id}", ['status' => $status]);
        }

        return $id;
    }

    public function test_shipment_intelligence_widget_classifies_delayed_near_deadline_and_released(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerA = $this->createCustomer($token, 'a');
        $customerB = $this->createCustomer($token, 'b');

        // Delayed: ETA in the past, still in flight.
        $this->createShipment($token, $customerA, now()->subDays(5)->toDateString());

        // Near-deadline: ETA within the next 3 days, still in flight.
        $this->createShipment($token, $customerA, now()->addDays(2)->toDateString());

        // Not yet due: ETA far in the future.
        $this->createShipment($token, $customerB, now()->addDays(30)->toDateString());

        // Released: delivered, should not count as delayed even though ETA has passed.
        $this->createShipment($token, $customerB, now()->subDays(10)->toDateString(), 'delivered');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $response->assertJsonPath('data.widgets.shipment_intelligence.active', 3);
        $response->assertJsonPath('data.widgets.shipment_intelligence.released', 1);
        $response->assertJsonPath('data.widgets.shipment_intelligence.delayed', 1);
        $response->assertJsonPath('data.widgets.shipment_intelligence.near_deadline', 1);
        $response->assertJsonPath('data.widgets.shipment_intelligence.customers_served', 2);
    }

    public function test_shipment_intelligence_widget_is_absent_without_shipments_permission(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Driver Dan',
                'email' => 'driver@acme.test',
                'role' => 'Driver',
                'password' => 'DriverPass123',
            ])->assertCreated();

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        $driverToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@acme.test',
            'password' => 'DriverPass123',
        ])->json('token');

        $response = $this->withHeader('Authorization', "Bearer {$driverToken}")
            ->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $response->assertJsonMissingPath('data.widgets.shipment_intelligence');
    }
}
