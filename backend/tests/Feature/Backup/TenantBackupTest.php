<?php

namespace Tests\Feature\Backup;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function registerTenant(string $email = 'owner@acme.test', string $companyName = 'Acme Logistics'): array
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

    private function createCustomer(string $token, string $companyName): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', [
                'company_name' => $companyName,
                'email' => strtolower(str_replace(' ', '', $companyName)).'@customer.test',
                'status' => 'active',
            ]);

        return $response->json('data.id');
    }

    public function test_owner_can_export_a_backup_containing_tenant_data(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $this->createCustomer($token, 'Shipper Co');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->get('/api/v1/backup/export');

        $response->assertOk();
        $backup = json_decode($response->streamedContent(), true);

        $this->assertEquals($registration['user']['tenant_id'], $backup['tenant_id']);
        $this->assertNotEmpty(
            collect($backup['tables']['customers'])->firstWhere('company_name', 'Shipper Co')
        );
    }

    public function test_restore_reverts_a_deleted_customer_and_a_workflow_with_its_steps(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token, 'Shipper Co');

        $workflow = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/workflows/definitions', [
                'name' => 'Expense Approval',
                'subject_type' => 'expense',
                'is_active' => true,
                'steps' => [
                    ['approver_role' => 'Operations Manager'],
                    ['approver_role' => 'Finance Manager'],
                ],
            ])->json('data');

        $backupResponse = $this->withHeader('Authorization', "Bearer {$token}")->get('/api/v1/backup/export');
        $backupContents = $backupResponse->streamedContent();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/crm/customers/{$customerId}")
            ->assertNoContent();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/workflows/definitions/{$workflow['id']}")
            ->assertNoContent();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/crm/customers')
            ->assertJsonCount(0, 'data');

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('backup.json', $backupContents);

        $restoreResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/backup/restore', ['file' => $file]);

        $restoreResponse->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/crm/customers')
            ->assertOk()
            ->assertJsonFragment(['company_name' => 'Shipper Co']);

        $restoredWorkflows = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/workflows/definitions')
            ->json('data');

        $this->assertCount(1, $restoredWorkflows);
        $this->assertCount(2, $restoredWorkflows[0]['steps']);
    }

    public function test_restoring_another_tenants_backup_is_rejected(): void
    {
        $regA = $this->registerTenant('owner-a@acme.test', 'Acme A');
        $backupA = $this->withHeader('Authorization', "Bearer {$regA['token']}")
            ->get('/api/v1/backup/export')
            ->streamedContent();

        $this->registerTenant('owner-b@other.test', 'Other B');

        // Two different bearer tokens in one test method resolve the SECOND
        // as the FIRST due to Laravel's auth guard caching — swap identities
        // via Sanctum::actingAs() instead. See project memory / other tests
        // in this suite (e.g. ReportExportTest, PortalTest) for the same fix.
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'owner-b@other.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('backup.json', $backupA);

        $this->withHeader('Accept', 'application/json')
            ->post('/api/v1/backup/restore', ['file' => $file])
            ->assertUnprocessable();
    }

    public function test_user_without_backup_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'No Access',
                'email' => 'noaccess@acme.test',
                'role' => 'Driver',
                'password' => 'SecurePass123',
            ])->assertCreated();

        app(TenantContext::class)->clear();
        $restrictedUser = User::where('email', 'noaccess@acme.test')->firstOrFail();
        Sanctum::actingAs($restrictedUser, ['*']);

        $this->get('/api/v1/backup/export')->assertForbidden();
    }
}
