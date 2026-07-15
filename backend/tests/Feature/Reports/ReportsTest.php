<?php

namespace Tests\Feature\Reports;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
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

    public function test_reports_overview_aggregates_across_modules(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $customerId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', ['company_name' => 'Shipper Co', 'status' => 'active'])
            ->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/clearing/files', ['customer_id' => $customerId, 'direction' => 'import', 'mode' => 'sea'])
            ->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 1000,
                'total_amount' => 1000,
                'status' => 'sent',
            ])->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/overview');

        $response->assertOk();
        $response->assertJsonPath('crm.customers_total', 1);
        $response->assertJsonPath('clearing.total', 1);
        $response->assertJsonPath('clearing.by_status.pending', 1);
        $response->assertJsonPath('finance.invoices_total', 1);
        $this->assertEquals(1000, $response->json('finance.outstanding_amount'));
    }

    public function test_profit_report_rolls_up_revenue_and_cost_per_shipment(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $customerId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', ['company_name' => 'Shipper Co', 'status' => 'active'])
            ->json('data.id');

        $shipmentId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', ['customer_id' => $customerId, 'direction' => 'import', 'mode' => 'sea'])
            ->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'shipment_id' => $shipmentId,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 1000,
                'total_amount' => 1000,
                'status' => 'paid',
            ])->assertCreated();

        $expenseId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/expenses', [
                'shipment_id' => $shipmentId,
                'category' => 'trucking',
                'description' => 'Trucking',
                'amount' => 200,
                'expense_date' => now()->toDateString(),
            ])->json('data.id');
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/submit")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$expenseId}/approve")->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/profit');

        $response->assertOk();
        $response->assertJsonCount(1, 'rows');
        $response->assertJsonPath('rows.0.shipment_id', $shipmentId);
        $response->assertJsonPath('rows.0.revenue', 1000);
        $response->assertJsonPath('rows.0.cost', 200);
        $response->assertJsonPath('rows.0.profit', 800);
        $response->assertJsonPath('totals.profit', 800);
    }

    public function test_customs_report_summarizes_clearance_and_duty(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $customerId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', ['company_name' => 'Shipper Co', 'status' => 'active'])
            ->json('data.id');

        $fileId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/clearing/files', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
                'customs_office' => 'Dar es Salaam Port',
                'duty_amount' => 500,
                'vat_amount' => 180,
                'customs_value' => 10000,
            ])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/clearing/files/{$fileId}", [
                'status' => 'cleared',
                'cleared_date' => now()->toDateString(),
                'assessment_status' => 'assessed',
            ])->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/customs');

        $response->assertOk();
        $response->assertJsonPath('total_declarations', 1);
        $response->assertJsonPath('total_duty', 500);
        $response->assertJsonPath('total_vat', 180);
        $response->assertJsonPath('by_customs_office.Dar es Salaam Port', 1);
        $response->assertJsonPath('by_assessment_status.assessed', 1);
        $this->assertNotNull($response->json('avg_clearance_days'));
    }

    public function test_tax_report_groups_vat_and_duty_by_month(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $customerId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', ['company_name' => 'Shipper Co', 'status' => 'active'])
            ->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 1000,
                'tax_amount' => 160,
                'total_amount' => 1160,
                'status' => 'paid',
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/clearing/files', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
                'duty_amount' => 300,
            ])->assertCreated()
            ->json('data.id');

        $fileId = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/clearing/files')->json('data.0.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/clearing/files/{$fileId}", ['cleared_date' => now()->toDateString()])
            ->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/tax');

        $response->assertOk();
        $response->assertJsonPath('totals.vat_collected', 160);
        $response->assertJsonPath('totals.duty_paid', 300);
    }

    public function test_user_without_reports_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/reports/overview')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }
}
