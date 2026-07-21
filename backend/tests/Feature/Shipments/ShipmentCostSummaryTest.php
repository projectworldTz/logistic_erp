<?php

namespace Tests\Feature\Shipments;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentCostSummaryTest extends TestCase
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
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', [
                'company_name' => 'Shipper Co',
                'email' => 'ops@shipperco.test',
                'status' => 'active',
            ])->json('data.id');
    }

    private function createShipment(string $token, int $customerId): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
            ])->json('data.id');
    }

    private function createInvoice(string $token, int $customerId, int $shipmentId, float $amount, string $status): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'shipment_id' => $shipmentId,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => $amount,
                'total_amount' => $amount,
                'status' => $status,
            ])->json('data.id');
    }

    private function createExpense(string $token, int $shipmentId, float $amount, string $category = 'trucking'): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/expenses', [
                'shipment_id' => $shipmentId,
                'category' => $category,
                'description' => "Expense for {$category}",
                'amount' => $amount,
                'expense_date' => now()->toDateString(),
            ])->json('data.id');
    }

    public function test_cost_summary_computes_revenue_cost_profit_and_margin(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $shipmentId = $this->createShipment($token, $customerId);

        $this->createInvoice($token, $customerId, $shipmentId, 1000, 'sent');
        $this->createInvoice($token, $customerId, $shipmentId, 500, 'paid');

        $approvedExpenseId = $this->createExpense($token, $shipmentId, 200, 'trucking');
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$approvedExpenseId}/submit")->assertOk();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$approvedExpenseId}/approve")->assertOk();

        $pendingExpenseId = $this->createExpense($token, $shipmentId, 50, 'port_fees');
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/finance/expenses/{$pendingExpenseId}/submit")->assertOk();

        // Draft expense - should not count toward confirmed or pending cost.
        $this->createExpense($token, $shipmentId, 30, 'documentation');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/shipments/items/{$shipmentId}/cost-summary");

        $response->assertOk();
        $response->assertJsonPath('data.revenue.billed', 1500);
        $response->assertJsonPath('data.revenue.collected', 500);
        $response->assertJsonPath('data.cost.confirmed', 200);
        $response->assertJsonPath('data.cost.pending', 50);
        $response->assertJsonPath('data.profit', 1300);
        $response->assertJsonPath('data.margin_percent', 86.67);
        $response->assertJsonCount(1, 'data.cost_breakdown');
        $response->assertJsonPath('data.cost_breakdown.0.category', 'trucking');
        $response->assertJsonPath('data.cost_breakdown.0.amount', 200);
        $response->assertJsonCount(2, 'data.invoices');
        $response->assertJsonCount(3, 'data.expenses');
    }

    public function test_cost_summary_with_no_financials_returns_zeroed_summary(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $shipmentId = $this->createShipment($token, $customerId);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/shipments/items/{$shipmentId}/cost-summary");

        $response->assertOk();
        $response->assertJsonPath('data.revenue.billed', 0);
        $response->assertJsonPath('data.cost.confirmed', 0);
        $response->assertJsonPath('data.profit', 0);
        $response->assertJsonPath('data.margin_percent', null);
    }

    public function test_user_without_costs_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $shipmentId = $this->createShipment($token, $customerId);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Warehouse Staffer',
                'email' => 'staff@acme.test',
                'roles' => ['Warehouse Staff'],
                'password' => 'StaffPass123',
            ])->assertCreated();

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@acme.test',
            'password' => 'StaffPass123',
        ]);
        $staffToken = $loginResponse->json('token');

        $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->getJson("/api/v1/shipments/items/{$shipmentId}/cost-summary")
            ->assertForbidden();
    }
}
