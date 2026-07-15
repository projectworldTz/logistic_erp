<?php

namespace Tests\Feature\Quotations;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
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

    public function test_quotation_line_items_drive_the_computed_totals(): void
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
                'tax_amount' => 100,
                'items' => [
                    ['description' => 'Ocean freight', 'quantity' => 1, 'unit_price' => 800],
                    ['description' => 'Documentation fee', 'quantity' => 2, 'unit_price' => 50],
                ],
            ]);

        $create->assertCreated();
        $create->assertJsonPath('data.subtotal', '900.00');
        $create->assertJsonPath('data.total_amount', '1000.00');
        $create->assertJsonCount(2, 'data.items');
        $create->assertJsonPath('data.items.0.description', 'Ocean freight');
        $create->assertJsonPath('data.items.1.amount', '100.00');

        $quotationId = $create->json('data.id');

        // Replacing the items recomputes the totals from the new set.
        $update = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/quotations/items/{$quotationId}", [
                'items' => [
                    ['description' => 'Air freight', 'quantity' => 1, 'unit_price' => 500],
                ],
            ]);

        $update->assertOk();
        $update->assertJsonPath('data.subtotal', '500.00');
        $update->assertJsonPath('data.total_amount', '600.00');
        $update->assertJsonCount(1, 'data.items');
    }

    public function test_accepted_quotation_can_be_converted_to_a_shipment_exactly_once(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $quotationId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/quotations/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
                'origin_port' => 'Dar es Salaam',
                'destination_port' => 'Dubai',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'subtotal' => 1000,
                'total_amount' => 1000,
            ])->json('data.id');

        // Not yet accepted.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/quotations/items/{$quotationId}/convert-to-shipment")
            ->assertStatus(422);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/quotations/items/{$quotationId}", ['status' => 'accepted'])
            ->assertOk();

        $convert = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/quotations/items/{$quotationId}/convert-to-shipment");

        $convert->assertCreated();
        $convert->assertJsonPath('data.quotation_id', $quotationId);
        $convert->assertJsonPath('data.customer_id', $customerId);
        $convert->assertJsonPath('data.origin_port', 'Dar es Salaam');
        $convert->assertJsonPath('data.destination_port', 'Dubai');

        // Cannot convert the same quotation twice.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/quotations/items/{$quotationId}/convert-to-shipment")
            ->assertStatus(409);
    }

    private function makeUserWithRole(int $tenantId, string $role, string $email): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $user = User::factory()->create(['tenant_id' => $tenantId, 'email' => $email]);
        $user->assignRole($role);

        return $user;
    }

    private function actingAsTenantUser(User $user, int $tenantId): void
    {
        app(TenantContext::class)->clear();
        Sanctum::actingAs($user, ['*']);
        app(TenantContext::class)->set($tenantId);
    }

    public function test_quotation_above_threshold_requires_sales_manager_approval_before_it_can_be_sent(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/workflows/definitions', [
                'name' => 'Quotation Pricing Sign-off',
                'subject_type' => 'quotation',
                'is_active' => true,
                'min_amount' => 5000,
                'steps' => [
                    ['approver_role' => 'Sales Manager'],
                ],
            ])->assertCreated();

        $quotationId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/quotations/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'subtotal' => 8000,
                'total_amount' => 8000,
            ])->json('data.id');

        $submit = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/quotations/items/{$quotationId}/submit");
        $submit->assertOk();
        $submit->assertJsonPath('data.approval_request.status', 'pending');
        $submit->assertJsonPath('data.status', 'draft');

        // The sales executive who submitted it cannot also approve it.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/quotations/items/{$quotationId}/approve")
            ->assertForbidden();

        $salesManager = $this->makeUserWithRole($tenantId, 'Sales Manager', 'sm@acme.test');
        $this->actingAsTenantUser($salesManager, $tenantId);

        $approve = $this->postJson("/api/v1/quotations/items/{$quotationId}/approve");
        $approve->assertOk();
        $approve->assertJsonPath('data.status', 'sent');
        $approve->assertJsonPath('data.approval_request.status', 'approved');
    }

    public function test_direct_status_update_to_sent_is_blocked_when_pricing_approval_is_required(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/workflows/definitions', [
                'name' => 'Quotation Pricing Sign-off',
                'subject_type' => 'quotation',
                'is_active' => true,
                'min_amount' => 5000,
                'steps' => [
                    ['approver_role' => 'Sales Manager'],
                ],
            ])->assertCreated();

        $quotationId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/quotations/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'subtotal' => 8000,
                'total_amount' => 8000,
            ])->json('data.id');

        // Trying to bypass the approval chain by setting status=sent directly is blocked,
        // even though nothing has been submitted for approval yet.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/quotations/items/{$quotationId}", ['status' => 'sent'])
            ->assertStatus(422);

        $this->assertDatabaseHas('quotations', ['id' => $quotationId, 'status' => 'draft']);
    }

    public function test_quotation_below_threshold_has_no_pending_approval_and_can_be_sent_directly(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/workflows/definitions', [
                'name' => 'Quotation Pricing Sign-off',
                'subject_type' => 'quotation',
                'is_active' => true,
                'min_amount' => 5000,
                'steps' => [
                    ['approver_role' => 'Sales Manager'],
                ],
            ])->assertCreated();

        $quotationId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/quotations/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'subtotal' => 1000,
                'total_amount' => 1000,
            ])->json('data.id');

        // Below the workflow's min_amount, so no approval request is opened.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/quotations/items/{$quotationId}/submit")
            ->assertOk()
            ->assertJsonPath('data.approval_request', null);

        // Direct status update still works exactly as before.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/quotations/items/{$quotationId}", ['status' => 'sent'])
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');
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
