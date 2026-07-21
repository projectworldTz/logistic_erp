<?php

namespace Tests\Feature\Fleet;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleLogTest extends TestCase
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

    private function createVehicle(string $token): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fleet/vehicles', [
                'registration_number' => 'KDA 123X',
                'vehicle_type' => 'truck',
            ])->json('data.id');
    }

    public function test_owner_can_log_maintenance_fuel_insurance_and_trip_entries(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $vehicleId = $this->createVehicle($token);

        $maintenance = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/fleet/vehicles/{$vehicleId}/logs", [
                'type' => 'maintenance',
                'log_date' => now()->toDateString(),
                'description' => 'Brake pad replacement',
                'cost' => 150,
                'odometer_km' => 42000,
            ]);
        $maintenance->assertCreated();
        $maintenance->assertJsonPath('data.type', 'maintenance');
        $maintenance->assertJsonPath('data.cost', '150.00');

        $fuel = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/fleet/vehicles/{$vehicleId}/logs", [
                'type' => 'fuel',
                'log_date' => now()->toDateString(),
                'description' => 'Diesel refill',
                'cost' => 80,
                'liters' => 60,
                'odometer_km' => 42100,
            ]);
        $fuel->assertCreated();
        $fuel->assertJsonPath('data.liters', '60.00');

        $insurance = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/fleet/vehicles/{$vehicleId}/logs", [
                'type' => 'insurance',
                'log_date' => now()->toDateString(),
                'description' => 'Annual comprehensive cover',
                'cost' => 900,
                'policy_number' => 'POL-9001',
                'expiry_date' => now()->addYear()->toDateString(),
            ]);
        $insurance->assertCreated();
        $insurance->assertJsonPath('data.policy_number', 'POL-9001');

        $trip = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/fleet/vehicles/{$vehicleId}/logs", [
                'type' => 'trip',
                'log_date' => now()->toDateString(),
                'description' => 'Dar to Dodoma delivery run',
                'origin' => 'Dar es Salaam',
                'destination' => 'Dodoma',
                'distance_km' => 450,
            ]);
        $trip->assertCreated();
        $trip->assertJsonPath('data.origin', 'Dar es Salaam');
        $trip->assertJsonPath('data.distance_km', '450.00');

        $index = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/fleet/vehicles/{$vehicleId}/logs");
        $index->assertOk();
        $index->assertJsonCount(4, 'data');

        $logId = $maintenance->json('data.id');
        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/fleet/vehicles/{$vehicleId}/logs/{$logId}")
            ->assertNoContent();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/fleet/vehicles/{$vehicleId}/logs")
            ->assertJsonCount(3, 'data');
    }

    public function test_vehicle_logs_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $vehicleIdA = $this->createVehicle($registrationA['token']);

        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson("/api/v1/fleet/vehicles/{$vehicleIdA}/logs", [
                'type' => 'fuel',
                'log_date' => now()->toDateString(),
                'description' => 'Diesel refill',
                'cost' => 80,
            ])->assertCreated();

        $registrationB = $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);

        $vehicleIdB = $this->createVehicle($registrationB['token']);

        $this->getJson("/api/v1/fleet/vehicles/{$vehicleIdB}/logs")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // A tenant-B user cannot see tenant-A's vehicle at all (404 via route-model binding + TenantScope).
        $this->getJson("/api/v1/fleet/vehicles/{$vehicleIdA}/logs")
            ->assertNotFound();
    }

    public function test_user_without_fleet_manage_permission_cannot_add_a_log(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $vehicleId = $this->createVehicle($token);

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
            ->postJson("/api/v1/fleet/vehicles/{$vehicleId}/logs", [
                'type' => 'fuel',
                'log_date' => now()->toDateString(),
                'description' => 'Diesel refill',
            ])
            ->assertForbidden();
    }
}
