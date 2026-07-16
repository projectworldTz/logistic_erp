<?php

namespace Tests\Feature\Crm;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplianceDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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
            ->postJson('/api/v1/crm/customers', ['company_name' => 'Shipper Co', 'status' => 'active'])
            ->json('data.id');
    }

    public function test_owner_can_upload_and_list_compliance_documents(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/v1/crm/customers/{$customerId}/compliance-documents", [
                'document_type' => 'tax_certificate',
                'document_number' => 'TIN-12345',
                'issue_date' => now()->subYear()->toDateString(),
                'expiry_date' => now()->addYear()->toDateString(),
                'file' => UploadedFile::fake()->create('tin.pdf', 100, 'application/pdf'),
                'notes' => 'Renewed annually',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.document_type', 'tax_certificate');
        $response->assertJsonPath('data.status', 'valid');
        $this->assertNotNull($response->json('data.file_url'));

        $index = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/crm/customers/{$customerId}/compliance-documents");
        $index->assertOk();
        $index->assertJsonCount(1, 'data');
    }

    public function test_status_is_computed_from_expiry_date(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $expired = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/crm/customers/{$customerId}/compliance-documents", [
                'document_type' => 'trading_license',
                'expiry_date' => now()->subDay()->toDateString(),
            ]);
        $expired->assertJsonPath('data.status', 'expired');

        $expiringSoon = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/crm/customers/{$customerId}/compliance-documents", [
                'document_type' => 'business_registration',
                'expiry_date' => now()->addDays(10)->toDateString(),
            ]);
        $expiringSoon->assertJsonPath('data.status', 'expiring_soon');

        $noExpiry = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/crm/customers/{$customerId}/compliance-documents", [
                'document_type' => 'authorized_signatory_id',
            ]);
        $noExpiry->assertJsonPath('data.status', 'no_expiry');
    }

    public function test_owner_can_delete_a_compliance_document(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $documentId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/crm/customers/{$customerId}/compliance-documents", [
                'document_type' => 'other',
            ])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->delete("/api/v1/crm/customers/{$customerId}/compliance-documents/{$documentId}")
            ->assertNoContent();

        $this->assertDatabaseCount('customer_compliance_documents', 0);
    }

    public function test_compliance_documents_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $customerAId = $this->createCustomer($registrationA['token']);
        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson("/api/v1/crm/customers/{$customerAId}/compliance-documents", ['document_type' => 'other'])
            ->assertCreated();

        $this->registerTenant('owner-b@acme.test', 'Acme B');
        app(TenantContext::class)->clear();
        $ownerB = User::where('email', 'owner-b@acme.test')->firstOrFail();
        Sanctum::actingAs($ownerB, ['*']);

        $response = $this->getJson("/api/v1/crm/customers/{$customerAId}/compliance-documents");

        $response->assertNotFound();
    }

    public function test_user_without_compliance_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Restricted User',
                'email' => 'restricted@acme.test',
                'role' => 'Document Controller',
                'password' => 'RestrictedPass123',
            ])->assertCreated();

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        $restrictedToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'restricted@acme.test',
            'password' => 'RestrictedPass123',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$restrictedToken}")
            ->getJson("/api/v1/crm/customers/{$customerId}/compliance-documents")
            ->assertForbidden();
    }
}
