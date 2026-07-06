<?php

namespace Tests\Feature\Portal;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalTest extends TestCase
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

    private function createCustomer(string $staffToken, string $companyName): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/crm/customers', [
                'company_name' => $companyName,
                'email' => strtolower(str_replace(' ', '', $companyName)).'@customer.test',
                'status' => 'active',
            ]);

        return $response->json('data.id');
    }

    private function invitePortalUser(string $staffToken, int $customerId, string $email): array
    {
        $response = $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/users', [
                'name' => 'Portal User',
                'email' => $email,
                'customer_id' => $customerId,
                'role' => 'Customer Portal User',
                'password' => 'PortalPass123',
            ]);

        $response->assertCreated();

        return $response->json('data');
    }

    public function test_portal_user_can_log_in_and_see_only_their_own_shipment(): void
    {
        $registration = $this->registerTenant();
        $staffToken = $registration['token'];
        $customerId = $this->createCustomer($staffToken, 'Shipper Co');
        $this->invitePortalUser($staffToken, $customerId, 'portal@shipperco.test');

        $shipment = $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
            ])->json('data');

        // Every prior authenticated bearer-token call in this test permanently
        // swapped the resolved default auth guard to 'sanctum' (stateless,
        // no ->attempt()) — reset both the config and the cached guard
        // before this login call, or Auth::attempt() throws. See project
        // memory: "auth:sanctum middleware permanently swaps the default
        // auth guard mid-test."
        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'portal@shipperco.test',
            'password' => 'PortalPass123',
        ]);

        $login->assertOk();
        $this->assertEquals($customerId, $login->json('user.customer_id'));
        $portalToken = $login->json('token');

        $this->withHeader('Authorization', "Bearer {$portalToken}")
            ->getJson('/api/v1/portal/shipments')
            ->assertOk()
            ->assertJsonFragment(['id' => $shipment['id']]);
    }

    public function test_staff_user_cannot_access_portal_routes(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/portal/dashboard/summary')
            ->assertForbidden();
    }

    public function test_portal_user_cannot_see_another_customers_shipment_in_the_same_tenant(): void
    {
        $registration = $this->registerTenant();
        $staffToken = $registration['token'];

        $customerA = $this->createCustomer($staffToken, 'Shipper A');
        $customerB = $this->createCustomer($staffToken, 'Shipper B');
        $this->invitePortalUser($staffToken, $customerA, 'portal-a@test.test');
        $this->invitePortalUser($staffToken, $customerB, 'portal-b@test.test');

        $shipmentB = $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerB,
                'direction' => 'import',
                'mode' => 'sea',
            ])->json('data');

        $invoiceB = $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerB,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 100,
                'total_amount' => 100,
            ])->json('data');

        app(TenantContext::class)->clear();
        $portalUserA = User::where('email', 'portal-a@test.test')->firstOrFail();
        Sanctum::actingAs($portalUserA, ['*']);

        // Customer A's portal user must not be able to fetch Customer B's
        // records by ID, even though both belong to the SAME tenant — this
        // is the direct test for the "one level down" IDOR the plan called out.
        $this->getJson("/api/v1/portal/shipments/{$shipmentB['id']}")->assertNotFound();
        $this->getJson("/api/v1/portal/invoices/{$invoiceB['id']}")->assertNotFound();

        $this->getJson('/api/v1/portal/shipments')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_portal_user_can_approve_a_sent_quotation_but_not_a_draft_one(): void
    {
        $registration = $this->registerTenant();
        $staffToken = $registration['token'];
        $customerId = $this->createCustomer($staffToken, 'Shipper Co');
        $this->invitePortalUser($staffToken, $customerId, 'portal@shipperco.test');

        $draftQuotation = $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/quotations/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'subtotal' => 100,
                'total_amount' => 100,
            ])->json('data');

        $sentQuotation = $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/quotations/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'subtotal' => 200,
                'total_amount' => 200,
                'status' => 'sent',
            ])->json('data');

        app(TenantContext::class)->clear();
        $portalUser = User::where('email', 'portal@shipperco.test')->firstOrFail();
        Sanctum::actingAs($portalUser, ['*']);

        $this->postJson("/api/v1/portal/quotations/{$draftQuotation['id']}/approve")
            ->assertUnprocessable();

        $this->postJson("/api/v1/portal/quotations/{$sentQuotation['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
    }

    public function test_portal_document_upload_is_forced_to_own_customer_id(): void
    {
        $registration = $this->registerTenant();
        $staffToken = $registration['token'];
        $customerA = $this->createCustomer($staffToken, 'Shipper A');
        $customerB = $this->createCustomer($staffToken, 'Shipper B');
        $this->invitePortalUser($staffToken, $customerA, 'portal-a@test.test');

        app(TenantContext::class)->clear();
        $portalUserA = User::where('email', 'portal-a@test.test')->firstOrFail();
        Sanctum::actingAs($portalUserA, ['*']);

        $file = \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->withHeader('Accept', 'application/json')
            ->post('/api/v1/portal/documents', [
                'file' => $file,
                'customer_id' => $customerB, // attempted spoof — must be ignored
            ]);

        $response->assertCreated();
        $this->assertEquals($customerA, $response->json('data.customer_id'));
    }
}
