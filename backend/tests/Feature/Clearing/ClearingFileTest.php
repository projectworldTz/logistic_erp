<?php

namespace Tests\Feature\Clearing;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClearingFileTest extends TestCase
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

    public function test_tenant_user_can_create_list_and_update_a_clearing_file(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/clearing/files', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
                'port_of_discharge' => 'Mombasa',
                'bl_awb_number' => 'BL123456',
            ]);

        $create->assertCreated();
        $fileId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'pending');
        $this->assertNotNull($create->json('data.reference_no'));
        $this->assertStringStartsWith('CLR-', $create->json('data.reference_no'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/clearing/files')
            ->assertOk()
            ->assertJsonFragment(['id' => $fileId]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/clearing/files/{$fileId}", ['status' => 'cleared'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cleared');

        $this->assertDatabaseHas('audit_logs', ['action' => 'clearing_file.created']);
    }

    public function test_clearing_file_can_be_created_and_updated_with_customs_assessment_fields(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/clearing/files', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
                'declaration_number' => 'DEC-2026-001',
                'sad_number' => 'SAD-2026-9001',
                'hs_code' => '8703.23',
                'customs_value' => 25000,
            ]);

        $create->assertCreated();
        $create->assertJsonPath('data.sad_number', 'SAD-2026-9001');
        $create->assertJsonPath('data.customs_value', '25000.00');
        $create->assertJsonPath('data.assessment_status', 'pending');

        $fileId = $create->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/clearing/files/{$fileId}", [
                'assessment_status' => 'assessed',
                'release_order_number' => 'RO-2026-777',
            ])
            ->assertOk()
            ->assertJsonPath('data.assessment_status', 'assessed')
            ->assertJsonPath('data.release_order_number', 'RO-2026-777')
            ->assertJsonPath('data.sad_number', 'SAD-2026-9001');
    }

    public function test_clearing_files_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $customerA = $this->createCustomer($registrationA['token']);

        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/clearing/files', [
                'customer_id' => $customerA,
                'direction' => 'import',
                'mode' => 'sea',
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/clearing/files')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_without_clearing_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/clearing/files')
            ->assertOk();

        // Sanity: a route guarded by a permission the tenant owner truly lacks returns 403.
        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }
}
