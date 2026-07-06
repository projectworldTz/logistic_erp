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
