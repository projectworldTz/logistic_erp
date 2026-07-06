<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceTest extends TestCase
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

    public function test_tenant_user_can_create_list_and_update_an_invoice(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 1000,
                'tax_amount' => 160,
                'total_amount' => 1160,
            ]);

        $create->assertCreated();
        $invoiceId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'draft');
        $this->assertNotNull($create->json('data.invoice_number'));
        $this->assertStringStartsWith('INV-', $create->json('data.invoice_number'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/finance/invoices')
            ->assertOk()
            ->assertJsonFragment(['id' => $invoiceId]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/finance/invoices/{$invoiceId}", ['status' => 'paid'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.created']);
    }

    public function test_due_date_before_issue_date_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->subDays(5)->toDateString(),
                'subtotal' => 1000,
                'total_amount' => 1000,
            ])->assertUnprocessable();
    }

    public function test_invoices_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $customerA = $this->createCustomer($registrationA['token']);

        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerA,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 1000,
                'total_amount' => 1000,
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/finance/invoices')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_without_finance_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/finance/invoices')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }

    public function test_user_can_download_invoice_pdf_and_a_paid_invoice_downloads_as_receipt(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 1000,
                'tax_amount' => 160,
                'total_amount' => 1160,
            ]);
        $invoiceId = $create->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/finance/invoices/{$invoiceId}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/finance/invoices/{$invoiceId}", ['status' => 'paid'])
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/finance/invoices/{$invoiceId}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_user_without_finance_view_permission_cannot_download_invoice_pdf(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 1000,
                'total_amount' => 1000,
            ]);
        $invoiceId = $create->json('data.id');

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $driver = User::factory()->create(['tenant_id' => $tenantId]);
        $driver->assignRole('Driver');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($driver, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->get("/api/v1/finance/invoices/{$invoiceId}/pdf")->assertForbidden();
    }
}
