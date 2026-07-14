<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTest extends TestCase
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

    public function test_owner_can_create_list_and_update_a_draft_expense(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/expenses', [
                'category' => 'customs_duty',
                'description' => 'Import duty payment',
                'amount' => 1000,
                'expense_date' => now()->toDateString(),
            ]);

        $create->assertCreated();
        $expenseId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'draft');
        $this->assertNotNull($create->json('data.expense_number'));
        $this->assertStringStartsWith('EXP-', $create->json('data.expense_number'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/finance/expenses')
            ->assertOk()
            ->assertJsonFragment(['id' => $expenseId]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/finance/expenses/{$expenseId}", ['amount' => 1200])
            ->assertOk()
            ->assertJsonPath('data.amount', '1200.00');

        $this->assertDatabaseHas('audit_logs', ['action' => 'expense.created']);
    }

    public function test_full_lifecycle_submit_approve_mark_paid(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $expenseId = $this->createExpense($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('audit_logs', ['action' => 'expense.submitted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense.approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense.paid']);
    }

    public function test_submitted_expense_can_be_rejected_with_a_reason(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $expenseId = $this->createExpense($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/submit")
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/reject", ['reason' => 'Missing receipt'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Missing receipt');

        // A rejected expense cannot then be approved.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/approve")
            ->assertStatus(409);
    }

    public function test_cannot_approve_a_draft_expense_or_mark_unapproved_expense_paid(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $expenseId = $this->createExpense($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/approve")
            ->assertStatus(409);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/mark-paid")
            ->assertStatus(409);
    }

    public function test_only_draft_expenses_can_be_edited_or_deleted(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $expenseId = $this->createExpense($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/submit")
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/finance/expenses/{$expenseId}", ['amount' => 999])
            ->assertStatus(409);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/finance/expenses/{$expenseId}")
            ->assertStatus(409);
    }

    public function test_expenses_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $this->createExpense($registrationA['token']);

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/finance/expenses')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_with_manage_but_not_approve_permission_cannot_approve(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];
        $expenseId = $this->createExpense($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/submit")
            ->assertOk();

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $officer = User::factory()->create(['tenant_id' => $tenantId]);
        $officer->assignRole('Clearing Officer');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($officer, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->postJson("/api/v1/finance/expenses/{$expenseId}/approve")->assertForbidden();
        // But a Clearing Officer can still create/manage expenses.
        $this->postJson('/api/v1/finance/expenses', [
            'category' => 'trucking',
            'description' => 'Delivery run',
            'amount' => 80,
            'expense_date' => now()->toDateString(),
        ])->assertCreated();
    }

    public function test_user_without_expenses_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $driver = User::factory()->create(['tenant_id' => $tenantId]);
        $driver->assignRole('Driver');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($driver, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->getJson('/api/v1/finance/expenses')->assertForbidden();
    }
}
