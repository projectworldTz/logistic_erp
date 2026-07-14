<?php

namespace Tests\Feature\Workflow;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
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

    private function createExpense(string $token, array $overrides = []): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/expenses', array_merge([
                'category' => 'trucking',
                'description' => 'Trucking from port to warehouse',
                'amount' => 250.50,
                'expense_date' => now()->toDateString(),
            ], $overrides));

        return $response->json('data.id');
    }

    private function createWorkflow(string $token, array $overrides = []): array
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/workflows/definitions', array_merge([
                'name' => 'Expense Approval',
                'subject_type' => 'expense',
                'is_active' => true,
                'steps' => [
                    ['approver_role' => 'Operations Manager'],
                    ['approver_role' => 'Finance Manager'],
                ],
            ], $overrides));

        return $response->json();
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

    public function test_owner_can_create_a_workflow_with_ordered_steps(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $response = $this->createWorkflow($token);

        $this->assertEquals('Expense Approval', $response['data']['name']);
        $this->assertCount(2, $response['data']['steps']);
        $this->assertEquals(1, $response['data']['steps'][0]['position']);
        $this->assertEquals('Operations Manager', $response['data']['steps'][0]['approver_role']);
        $this->assertEquals(2, $response['data']['steps'][1]['position']);
        $this->assertEquals('Finance Manager', $response['data']['steps'][1]['approver_role']);
    }

    public function test_expense_approval_falls_back_to_legacy_single_approver_when_no_workflow_configured(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $expenseId = $this->createExpense($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/submit")
            ->assertOk();

        // No workflow configured at all: owner (who holds expenses.items.approve) can approve directly.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_multi_step_workflow_requires_each_step_role_in_order(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];
        $this->createWorkflow($token);

        $expenseId = $this->createExpense($token);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/submit")
            ->assertOk();

        $financeManager = $this->makeUserWithRole($tenantId, 'Finance Manager', 'finance@acme.test');
        $opsManager = $this->makeUserWithRole($tenantId, 'Operations Manager', 'ops@acme.test');

        // Finance Manager tries to approve step 1 (reserved for Operations Manager) — forbidden.
        $this->actingAsTenantUser($financeManager, $tenantId);
        $this->postJson("/api/v1/finance/expenses/{$expenseId}/approve")->assertForbidden();

        // Operations Manager approves step 1 — expense stays 'submitted', now awaiting step 2.
        $this->actingAsTenantUser($opsManager, $tenantId);
        $step1 = $this->postJson("/api/v1/finance/expenses/{$expenseId}/approve")->assertOk();
        $step1->assertJsonPath('data.status', 'submitted');
        $step1->assertJsonPath('data.approval_request.status', 'pending');
        $step1->assertJsonPath('data.approval_request.current_step_position', 2);

        // Operations Manager cannot also approve step 2.
        $this->postJson("/api/v1/finance/expenses/{$expenseId}/approve")->assertForbidden();

        // Finance Manager approves step 2 — the whole chain is now approved.
        $this->actingAsTenantUser($financeManager, $tenantId);
        $final = $this->postJson("/api/v1/finance/expenses/{$expenseId}/approve")->assertOk();
        $final->assertJsonPath('data.status', 'approved');
        $final->assertJsonPath('data.approval_request.status', 'approved');
    }

    public function test_rejection_at_any_step_immediately_finalizes_as_rejected(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];
        $this->createWorkflow($token);

        $expenseId = $this->createExpense($token);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/submit")
            ->assertOk();

        $opsManager = $this->makeUserWithRole($tenantId, 'Operations Manager', 'ops@acme.test');
        $this->actingAsTenantUser($opsManager, $tenantId);

        $this->postJson("/api/v1/finance/expenses/{$expenseId}/reject", ['reason' => 'Not a valid business expense'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_amount_threshold_picks_the_most_specific_matching_workflow(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];

        // Default (no threshold): single step, Finance Manager only.
        $this->createWorkflow($token, [
            'name' => 'Default Expense Approval',
            'steps' => [['approver_role' => 'Finance Manager']],
        ]);

        // High-value (>= 1000): two steps, Operations Manager then Finance Manager.
        $this->createWorkflow($token, [
            'name' => 'High Value Expense Approval',
            'min_amount' => 1000,
        ]);

        $smallExpenseId = $this->createExpense($token, ['amount' => 200]);
        $largeExpenseId = $this->createExpense($token, ['amount' => 5000]);

        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/finance/expenses/{$smallExpenseId}/submit")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/finance/expenses/{$largeExpenseId}/submit")->assertOk();

        $financeManager = $this->makeUserWithRole($tenantId, 'Finance Manager', 'finance@acme.test');
        $this->actingAsTenantUser($financeManager, $tenantId);

        // Small expense: Finance Manager alone can finish it (single-step default workflow).
        $this->postJson("/api/v1/finance/expenses/{$smallExpenseId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        // Large expense: Finance Manager cannot act first — step 1 belongs to Operations Manager.
        $this->postJson("/api/v1/finance/expenses/{$largeExpenseId}/approve")->assertForbidden();
    }

    public function test_only_pending_requests_can_be_decided(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];
        $this->createWorkflow($token, [
            'name' => 'Single Step',
            'steps' => [['approver_role' => 'Finance Manager']],
        ]);

        $expenseId = $this->createExpense($token);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/submit")
            ->assertOk();

        $financeManager = $this->makeUserWithRole($tenantId, 'Finance Manager', 'finance@acme.test');
        $this->actingAsTenantUser($financeManager, $tenantId);

        $this->postJson("/api/v1/finance/expenses/{$expenseId}/approve")->assertOk();
        // Expense is already approved; the underlying request is no longer pending.
        $this->postJson("/api/v1/finance/expenses/{$expenseId}/approve")->assertStatus(409);
    }

    public function test_workflows_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $this->createWorkflow($registrationA['token']);

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/workflows/definitions')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_user_without_workflow_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];

        $driver = $this->makeUserWithRole($tenantId, 'Driver', 'driver@acme.test');
        $this->actingAsTenantUser($driver, $tenantId);

        $this->getJson('/api/v1/workflows/definitions')->assertForbidden();
    }
}
