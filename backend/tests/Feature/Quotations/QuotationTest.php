<?php

namespace Tests\Feature\Quotations;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuotationTest extends TestCase
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

    public function test_tenant_user_can_create_list_and_update_a_quotation(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/quotations/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'subtotal' => 1000,
                'tax_amount' => 160,
                'total_amount' => 1160,
            ]);

        $create->assertCreated();
        $quotationId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'draft');
        $this->assertNotNull($create->json('data.quotation_number'));
        $this->assertStringStartsWith('QT-', $create->json('data.quotation_number'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/quotations/items')
            ->assertOk()
            ->assertJsonFragment(['id' => $quotationId]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/quotations/items/{$quotationId}", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.created']);
    }

    public function test_valid_until_before_issue_date_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/quotations/items', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->subDays(3)->toDateString(),
                'subtotal' => 1000,
                'total_amount' => 1000,
            ])->assertUnprocessable();
    }

    public function test_quotations_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $customerA = $this->createCustomer($registrationA['token']);

        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/quotations/items', [
                'customer_id' => $customerA,
                'direction' => 'import',
                'mode' => 'sea',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'subtotal' => 1000,
                'total_amount' => 1000,
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/quotations/items')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_without_quotations_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/quotations/items')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }
}
