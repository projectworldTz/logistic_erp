<?php

namespace Tests\Feature\Tenant;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchRollupTest extends TestCase
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
                'email' => 'shipperco@customer.test',
                'status' => 'active',
            ]);

        return $response->json('data.id');
    }

    public function test_owner_sees_the_main_branch_and_an_unassigned_bucket(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/branches/rollup');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('branch_name');
        $this->assertContains('Main Branch', $names);
        $this->assertContains('Unassigned', $names);
    }

    public function test_rollup_reflects_shipments_and_invoices_assigned_to_a_branch(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $branchesResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/branches');
        $mainBranchId = $branchesResponse->json('data.0.id');

        $shipment = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'branch_id' => $mainBranchId,
                'direction' => 'export',
                'mode' => 'air',
            ])->json('data');

        $this->assertSame($mainBranchId, $shipment['branch_id']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'branch_id' => $mainBranchId,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'status' => 'paid',
                'subtotal' => 1000,
                'total_amount' => 1000,
            ])->assertCreated();

        $rollup = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/branches/rollup')
            ->json('data');

        $mainRow = collect($rollup)->firstWhere('branch_id', $mainBranchId);
        $this->assertSame(1, $mainRow['shipments_total']);
        $this->assertSame(1, $mainRow['invoices_total']);
        $this->assertEquals(1000, $mainRow['revenue_paid']);

        $unassignedRow = collect($rollup)->firstWhere('branch_name', 'Unassigned');
        $this->assertSame(0, $unassignedRow['shipments_total']);
        $this->assertSame(0, $unassignedRow['invoices_total']);
    }

    public function test_rollup_is_isolated_per_tenant(): void
    {
        $regA = $this->registerTenant('owner-a@acme.test', 'Acme Logistics');

        $this->registerTenant('owner-b@other.test', 'Other Co');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'owner-b@other.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);
        $this->postJson('/api/v1/crm/customers', [
            'company_name' => 'Other Customer',
            'email' => 'othercustomer@customer.test',
            'status' => 'active',
        ])->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$regA['token']}")
            ->getJson('/api/v1/branches/rollup');

        $names = collect($response->json('data'))->pluck('branch_name');
        $this->assertNotContains('Other Co Main Branch', $names);
        $this->assertCount(2, $names); // Acme's Main Branch + Unassigned, nothing from tenant B
    }

    public function test_user_without_branches_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'No Access',
                'email' => 'noaccess@acme.test',
                'role' => 'Driver',
                'password' => 'SecurePass123',
            ])->assertCreated();

        app(TenantContext::class)->clear();
        $restrictedUser = User::where('email', 'noaccess@acme.test')->firstOrFail();
        Sanctum::actingAs($restrictedUser, ['*']);

        $this->getJson('/api/v1/branches/rollup')->assertForbidden();
    }
}
