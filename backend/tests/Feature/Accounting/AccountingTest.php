<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccountingTest extends TestCase
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

    private function createAccount(string $token, string $code, string $name, string $type): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/accounting/accounts', [
                'code' => $code,
                'name' => $name,
                'type' => $type,
            ]);

        return $response->json('data.id');
    }

    public function test_tenant_user_can_create_accounts(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/accounting/accounts', [
                'code' => '1000',
                'name' => 'Cash',
                'type' => 'asset',
            ]);

        $response->assertCreated()->assertJsonPath('data.code', '1000');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/accounting/accounts')
            ->assertOk()
            ->assertJsonFragment(['code' => '1000']);
    }

    public function test_duplicate_account_code_within_tenant_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->createAccount($token, '1000', 'Cash', 'asset');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/accounting/accounts', ['code' => '1000', 'name' => 'Bank', 'type' => 'asset'])
            ->assertUnprocessable();
    }

    public function test_balanced_journal_entry_can_be_created_posted_and_shows_in_list(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $cash = $this->createAccount($token, '1000', 'Cash', 'asset');
        $revenue = $this->createAccount($token, '4000', 'Freight Revenue', 'revenue');

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/accounting/journal-entries', [
                'entry_date' => now()->toDateString(),
                'description' => 'Cash sale',
                'lines' => [
                    ['account_id' => $cash, 'debit' => 500, 'credit' => 0],
                    ['account_id' => $revenue, 'debit' => 0, 'credit' => 500],
                ],
            ]);

        $create->assertCreated();
        $entryId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'draft');
        $this->assertNotNull($create->json('data.entry_number'));
        $this->assertStringStartsWith('JE-', $create->json('data.entry_number'));
        $this->assertCount(2, $create->json('data.lines'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/accounting/journal-entries')
            ->assertOk()
            ->assertJsonFragment(['id' => $entryId]);

        $post = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/accounting/journal-entries/{$entryId}/post");

        $post->assertOk()->assertJsonPath('data.status', 'posted');
        $this->assertNotNull($post->json('data.posted_at'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'journal_entry.posted']);
    }

    public function test_unbalanced_journal_entry_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $cash = $this->createAccount($token, '1000', 'Cash', 'asset');
        $revenue = $this->createAccount($token, '4000', 'Freight Revenue', 'revenue');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/accounting/journal-entries', [
                'entry_date' => now()->toDateString(),
                'lines' => [
                    ['account_id' => $cash, 'debit' => 500, 'credit' => 0],
                    ['account_id' => $revenue, 'debit' => 0, 'credit' => 400],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    public function test_line_with_both_debit_and_credit_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $cash = $this->createAccount($token, '1000', 'Cash', 'asset');
        $revenue = $this->createAccount($token, '4000', 'Freight Revenue', 'revenue');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/accounting/journal-entries', [
                'entry_date' => now()->toDateString(),
                'lines' => [
                    ['account_id' => $cash, 'debit' => 500, 'credit' => 500],
                    ['account_id' => $revenue, 'debit' => 0, 'credit' => 500],
                ],
            ])
            ->assertUnprocessable();
    }

    public function test_posted_journal_entry_cannot_be_edited_or_deleted(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $cash = $this->createAccount($token, '1000', 'Cash', 'asset');
        $revenue = $this->createAccount($token, '4000', 'Freight Revenue', 'revenue');

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/accounting/journal-entries', [
                'entry_date' => now()->toDateString(),
                'lines' => [
                    ['account_id' => $cash, 'debit' => 500, 'credit' => 0],
                    ['account_id' => $revenue, 'debit' => 0, 'credit' => 500],
                ],
            ]);
        $entryId = $create->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/accounting/journal-entries/{$entryId}/post")
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/accounting/journal-entries/{$entryId}", ['description' => 'Edited after posting'])
            ->assertStatus(409);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/accounting/journal-entries/{$entryId}")
            ->assertStatus(409);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/accounting/journal-entries/{$entryId}/void")
            ->assertOk()
            ->assertJsonPath('data.status', 'voided');
    }

    public function test_account_with_journal_lines_cannot_be_deleted(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $cash = $this->createAccount($token, '1000', 'Cash', 'asset');
        $revenue = $this->createAccount($token, '4000', 'Freight Revenue', 'revenue');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/accounting/journal-entries', [
                'entry_date' => now()->toDateString(),
                'lines' => [
                    ['account_id' => $cash, 'debit' => 500, 'credit' => 0],
                    ['account_id' => $revenue, 'debit' => 0, 'credit' => 500],
                ],
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/accounting/accounts/{$cash}")
            ->assertStatus(409);
    }

    public function test_accountant_cannot_post_journal_entries(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];
        $cash = $this->createAccount($token, '1000', 'Cash', 'asset');
        $revenue = $this->createAccount($token, '4000', 'Freight Revenue', 'revenue');

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/accounting/journal-entries', [
                'entry_date' => now()->toDateString(),
                'lines' => [
                    ['account_id' => $cash, 'debit' => 500, 'credit' => 0],
                    ['account_id' => $revenue, 'debit' => 0, 'credit' => 500],
                ],
            ]);
        $entryId = $create->json('data.id');

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $accountant = User::factory()->create(['tenant_id' => $tenantId]);
        $accountant->assignRole('Accountant');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($accountant, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->postJson("/api/v1/accounting/journal-entries/{$entryId}/post")
            ->assertForbidden();
    }

    public function test_journal_entries_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $cash = $this->createAccount($registrationA['token'], '1000', 'Cash', 'asset');
        $revenue = $this->createAccount($registrationA['token'], '4000', 'Freight Revenue', 'revenue');

        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/accounting/journal-entries', [
                'entry_date' => now()->toDateString(),
                'lines' => [
                    ['account_id' => $cash, 'debit' => 500, 'credit' => 0],
                    ['account_id' => $revenue, 'debit' => 0, 'credit' => 500],
                ],
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/accounting/journal-entries')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
