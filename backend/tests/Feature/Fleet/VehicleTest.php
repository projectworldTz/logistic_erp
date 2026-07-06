<?php

namespace Tests\Feature\Fleet;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleTest extends TestCase
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

    public function test_tenant_user_can_create_list_and_update_a_vehicle(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fleet/vehicles', [
                'registration_number' => 'KDA 123X',
                'vehicle_type' => 'truck',
                'make' => 'Isuzu',
                'model' => 'FRR',
            ]);

        $create->assertCreated();
        $vehicleId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'active');
        $create->assertJsonPath('data.registration_number', 'KDA 123X');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/fleet/vehicles')
            ->assertOk()
            ->assertJsonFragment(['id' => $vehicleId]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/fleet/vehicles/{$vehicleId}", ['status' => 'in_maintenance'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_maintenance');

        $this->assertDatabaseHas('audit_logs', ['action' => 'vehicle.created']);
    }

    public function test_duplicate_registration_number_within_tenant_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fleet/vehicles', [
                'registration_number' => 'KDA 123X',
                'vehicle_type' => 'truck',
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/fleet/vehicles', [
                'registration_number' => 'KDA 123X',
                'vehicle_type' => 'van',
            ])->assertUnprocessable();
    }

    public function test_vehicles_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');

        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/fleet/vehicles', [
                'registration_number' => 'KDA 123X',
                'vehicle_type' => 'truck',
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/fleet/vehicles')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_without_fleet_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/fleet/vehicles')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }
}
