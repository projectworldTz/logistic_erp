<?php

namespace Tests\Feature\Shipments;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentSlaAlertTest extends TestCase
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
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', ['company_name' => 'Shipper Co', 'status' => 'active'])
            ->json('data.id');
    }

    private function createShipment(string $token, int $customerId, ?string $eta): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
                'eta' => $eta,
            ])->json('data.id');
    }

    public function test_sla_check_notifies_once_for_delayed_and_near_deadline_shipments(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $ownerId = $registration['user']['id'];
        $customerId = $this->createCustomer($token);

        $delayedId = $this->createShipment($token, $customerId, now()->subDays(3)->toDateString());
        $nearDeadlineId = $this->createShipment($token, $customerId, now()->addDay()->toDateString());
        $safeId = $this->createShipment($token, $customerId, now()->addDays(30)->toDateString());

        $first = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/sla-check');

        $first->assertOk();
        $first->assertJsonPath('delayed_alerted', 1);
        $first->assertJsonPath('near_deadline_alerted', 1);

        $this->assertDatabaseHas('shipments', ['id' => $delayedId, 'status' => 'booked']);
        $this->assertNotNull(\App\Models\Shipment::find($delayedId)->delayed_alert_sent_at);
        $this->assertNotNull(\App\Models\Shipment::find($nearDeadlineId)->near_deadline_alert_sent_at);
        $this->assertNull(\App\Models\Shipment::find($safeId)->delayed_alert_sent_at);

        $this->assertDatabaseCount('user_notifications', 2);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $ownerId, 'type' => 'shipment.delayed']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $ownerId, 'type' => 'shipment.near_deadline']);

        // Running again does not re-notify for the same shipments.
        $second = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/sla-check');

        $second->assertOk();
        $second->assertJsonPath('delayed_alerted', 0);
        $second->assertJsonPath('near_deadline_alerted', 0);
        $this->assertDatabaseCount('user_notifications', 2);
    }

    public function test_delivered_shipment_past_eta_is_not_flagged_as_delayed(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $shipmentId = $this->createShipment($token, $customerId, now()->subDays(5)->toDateString());
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/shipments/items/{$shipmentId}", ['status' => 'delivered'])
            ->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/sla-check');

        $response->assertOk();
        $response->assertJsonPath('delayed_alerted', 0);
    }

    public function test_sla_alerts_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $customerA = $this->createCustomer($registrationA['token']);
        $this->createShipment($registrationA['token'], $customerA, now()->subDays(2)->toDateString());

        $registrationB = $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);

        $response = $this->postJson('/api/v1/shipments/sla-check');

        $response->assertOk();
        $response->assertJsonPath('delayed_alerted', 0);
    }

    public function test_user_without_manage_permission_cannot_trigger_sla_check(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Driver Dan',
                'email' => 'driver@acme.test',
                'roles' => ['Driver'],
                'password' => 'DriverPass123',
            ])->assertCreated();

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        $driverToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@acme.test',
            'password' => 'DriverPass123',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$driverToken}")
            ->postJson('/api/v1/shipments/sla-check')
            ->assertForbidden();
    }
}
