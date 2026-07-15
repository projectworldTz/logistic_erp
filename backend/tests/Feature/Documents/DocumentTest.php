<?php

namespace Tests\Feature\Documents;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
        Storage::fake('public');
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

    public function test_tenant_user_can_upload_list_and_delete_a_document(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $file = UploadedFile::fake()->create('bill-of-lading.pdf', 100, 'application/pdf');

        $upload = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/documents/files', [
                'file' => $file,
                'category' => 'bill_of_lading',
                'description' => 'BL for shipment 123',
            ]);

        $upload->assertCreated();
        $documentId = $upload->json('data.id');
        $upload->assertJsonPath('data.file_name', 'bill-of-lading.pdf');
        $upload->assertJsonPath('data.category', 'bill_of_lading');
        $this->assertNotNull($upload->json('data.url'));

        $storedPath = \App\Models\Document::findOrFail($documentId)->file_path;
        Storage::disk('public')->assertExists($storedPath);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/documents/files')
            ->assertOk()
            ->assertJsonFragment(['id' => $documentId]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/documents/files/{$documentId}")
            ->assertStatus(204);

        Storage::disk('public')->assertMissing($storedPath);
        $this->assertDatabaseMissing('documents', ['id' => $documentId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.created']);
    }

    public function test_document_can_be_linked_to_a_shipment_and_uploaded_as_new_versions(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $customerId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', [
                'company_name' => 'Shipper Co',
                'email' => 'ops@shipperco.test',
                'status' => 'active',
            ])->json('data.id');

        $shipmentId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
            ])->json('data.id');

        $v1File = UploadedFile::fake()->create('packing-list.pdf', 100, 'application/pdf');
        $v1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/documents/files', [
                'file' => $v1File,
                'shipment_id' => $shipmentId,
                'category' => 'packing_list',
            ]);
        $v1->assertCreated();
        $v1->assertJsonPath('data.shipment_id', $shipmentId);
        $v1->assertJsonPath('data.version', 1);
        $v1->assertJsonPath('data.is_previewable', true);
        $documentId = $v1->json('data.id');

        $v2File = UploadedFile::fake()->create('packing-list-revised.pdf', 100, 'application/pdf');
        $v2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/documents/files', [
                'file' => $v2File,
                'shipment_id' => $shipmentId,
                'category' => 'packing_list',
                'parent_document_id' => $documentId,
            ]);
        $v2->assertCreated();
        $v2->assertJsonPath('data.version', 2);
        $v2->assertJsonPath('data.parent_document_id', $documentId);

        $versions = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/documents/files/{$documentId}/versions");
        $versions->assertOk();
        $versions->assertJsonCount(2, 'data');
        $versions->assertJsonPath('data.0.version', 2);
        $versions->assertJsonPath('data.1.version', 1);
    }

    public function test_disallowed_file_type_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Accept', 'application/json')
            ->post('/api/v1/documents/files', ['file' => $file])
            ->assertUnprocessable();
    }

    public function test_documents_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->post('/api/v1/documents/files', ['file' => $file])
            ->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/documents/files')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_without_documents_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/documents/files')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();
    }
}
