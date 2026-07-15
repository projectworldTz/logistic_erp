<?php

namespace Tests\Feature\Tracking;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentVerificationQrTest extends TestCase
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
            ->postJson('/api/v1/crm/customers', ['company_name' => 'Shipper Co', 'status' => 'active'])
            ->json('data.id');
    }

    public function test_release_order_qr_is_only_available_once_issued_and_verifies_publicly(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $fileId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/clearing/files', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
            ])->json('data.id');

        // Not yet issued.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/clearing/files/{$fileId}/release-order-qr")
            ->assertNotFound();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/clearing/files/{$fileId}", ['release_order_number' => 'RO-2026-777'])
            ->assertOk();

        $qr = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/clearing/files/{$fileId}/release-order-qr");
        $qr->assertOk();
        $qr->assertHeader('content-type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $qr->getContent());

        $token2 = \App\Models\ClearingFile::find($fileId)->release_order_token;
        $this->assertNotNull($token2);

        $verify = $this->getJson("/api/v1/public/verify/release-order/{$token2}");
        $verify->assertOk();
        $verify->assertJsonPath('data.release_order_number', 'RO-2026-777');
        $verify->assertJsonMissingPath('data.customer_id');

        $this->getJson('/api/v1/public/verify/release-order/not-a-real-token')->assertNotFound();
    }

    public function test_delivery_note_qr_is_only_available_once_delivered_and_verifies_publicly(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);

        $shipmentId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
                'destination_port' => 'Dar es Salaam',
            ])->json('data.id');

        // Not yet delivered.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/shipments/items/{$shipmentId}/delivery-note-qr")
            ->assertNotFound();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/shipments/items/{$shipmentId}/milestones", [
                'event_type' => 'delivered',
                'occurred_at' => now()->toDateTimeString(),
                'is_customer_visible' => true,
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/shipments/items/{$shipmentId}", ['status' => 'delivered'])
            ->assertOk();

        $qr = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/shipments/items/{$shipmentId}/delivery-note-qr");
        $qr->assertOk();
        $qr->assertHeader('content-type', 'image/svg+xml');

        $trackingCode = \App\Models\Shipment::find($shipmentId)->tracking_code;

        $verify = $this->getJson("/api/v1/public/verify/delivery-note/{$trackingCode}");
        $verify->assertOk();
        $verify->assertJsonPath('data.status', 'delivered');
        $verify->assertJsonPath('data.destination_port', 'Dar es Salaam');
        $this->assertNotNull($verify->json('data.delivered_at'));

        $this->getJson('/api/v1/public/verify/delivery-note/NOT-A-REAL-CODE')->assertNotFound();
    }
}
