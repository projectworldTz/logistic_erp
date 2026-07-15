<?php

namespace Tests\Feature\Reports;

use App\Models\Branch;
use App\Models\Company;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchScopedReportsTest extends TestCase
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

    private function createSecondBranch(int $tenantId): Branch
    {
        $company = Company::where('tenant_id', $tenantId)->firstOrFail();

        return Branch::query()->create([
            'tenant_id' => $tenantId,
            'company_id' => $company->id,
            'name' => 'Dar Branch',
            'code' => 'DAR',
            'is_default' => false,
            'address' => 'Dar es Salaam',
            'city' => 'Dar es Salaam',
            'country' => 'Tanzania',
            'timezone' => 'Africa/Dar_es_Salaam',
        ]);
    }

    public function test_overview_and_profit_filter_by_branch_when_requested(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];

        $mainBranchId = Branch::where('tenant_id', $tenantId)->where('is_default', true)->firstOrFail()->id;
        $secondBranch = $this->createSecondBranch($tenantId);

        $customerId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', ['company_name' => 'Shipper Co', 'status' => 'active'])
            ->json('data.id');

        $mainShipmentId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
                'branch_id' => $mainBranchId,
            ])->json('data.id');

        $secondShipmentId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
                'branch_id' => $secondBranch->id,
            ])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'shipment_id' => $mainShipmentId,
                'branch_id' => $mainBranchId,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 1000,
                'total_amount' => 1000,
                'status' => 'paid',
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'shipment_id' => $secondShipmentId,
                'branch_id' => $secondBranch->id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 500,
                'total_amount' => 500,
                'status' => 'sent',
            ])->assertCreated();

        // No branch filter: tenant-wide totals across both branches (backward compatible).
        $overviewAll = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/overview');
        $overviewAll->assertOk();
        $overviewAll->assertJsonPath('branch_id', null);
        $overviewAll->assertJsonPath('shipments.total', 2);
        $overviewAll->assertJsonPath('finance.invoices_total', 2);

        // Filtered to the main branch only.
        $overviewMain = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/overview?branch_id={$mainBranchId}");
        $overviewMain->assertOk();
        $overviewMain->assertJsonPath('branch_id', $mainBranchId);
        $overviewMain->assertJsonPath('shipments.total', 1);
        $overviewMain->assertJsonPath('finance.invoices_total', 1);
        $this->assertEquals(1000, $overviewMain->json('finance.paid_amount'));

        // Filtered to the second branch only.
        $overviewSecond = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/overview?branch_id={$secondBranch->id}");
        $overviewSecond->assertOk();
        $overviewSecond->assertJsonPath('shipments.total', 1);
        $overviewSecond->assertJsonPath('finance.invoices_total', 1);
        $this->assertEquals(500, $overviewSecond->json('finance.outstanding_amount'));

        // Profit report obeys the same filter.
        $profitAll = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/profit');
        $profitAll->assertOk();
        $profitAll->assertJsonCount(2, 'rows');

        $profitMain = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/profit?branch_id={$mainBranchId}");
        $profitMain->assertOk();
        $profitMain->assertJsonCount(1, 'rows');
        $profitMain->assertJsonPath('rows.0.shipment_id', $mainShipmentId);
        $profitMain->assertJsonPath('totals.revenue', 1000);
    }

    public function test_branch_filter_does_not_leak_across_tenants(): void
    {
        $registrationA = $this->registerTenant('owner-a@acme.test', 'Acme A');
        $registrationB = $this->registerTenant('owner-b@acme.test', 'Acme B');

        $tenantBId = $registrationB['user']['tenant_id'];
        $tenantBBranchId = Branch::where('tenant_id', $tenantBId)->where('is_default', true)->firstOrFail()->id;

        // Tenant A queries reports scoped to a branch_id that belongs to tenant B.
        $response = $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->getJson("/api/v1/reports/overview?branch_id={$tenantBBranchId}");

        $response->assertOk();
        // TenantScope on Shipment/Invoice means tenant A simply sees zero results, never tenant B's data.
        $response->assertJsonPath('shipments.total', 0);
        $response->assertJsonPath('finance.invoices_total', 0);
    }
}
