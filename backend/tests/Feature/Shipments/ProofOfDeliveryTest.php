<?php

namespace Tests\Feature\Shipments;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProofOfDeliveryTest extends TestCase
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

    private function createShipment(string $token): int
    {
        $customerId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', ['company_name' => 'Shipper Co', 'status' => 'active'])
            ->json('data.id');

        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', ['customer_id' => $customerId, 'direction' => 'import', 'mode' => 'sea'])
            ->json('data.id');
    }

    public function test_staff_can_capture_and_view_proof_of_delivery(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $shipmentId = $this->createShipment($token);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/v1/shipments/items/{$shipmentId}/proof-of-delivery", [
                'received_by_name' => 'John Mkasi',
                'signature' => UploadedFile::fake()->image('signature.png'),
                'photo' => UploadedFile::fake()->image('cargo.jpg'),
                'latitude' => -6.7924,
                'longitude' => 39.2083,
                'notes' => 'Left at reception',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.received_by_name', 'John Mkasi');
        $response->assertJsonPath('data.latitude', -6.7924);
        $this->assertNotNull($response->json('data.signature_url'));
        $this->assertNotNull($response->json('data.photo_url'));

        $show = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/shipments/items/{$shipmentId}/proof-of-delivery");
        $show->assertOk();
        $show->assertJsonPath('data.received_by_name', 'John Mkasi');
    }

    public function test_signature_is_required_but_photo_is_optional(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $shipmentId = $this->createShipment($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/shipments/items/{$shipmentId}/proof-of-delivery", [
                'received_by_name' => 'John Mkasi',
            ])
            ->assertJsonValidationErrors(['signature']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/v1/shipments/items/{$shipmentId}/proof-of-delivery", [
                'received_by_name' => 'John Mkasi',
                'signature' => UploadedFile::fake()->image('signature.png'),
            ])
            ->assertCreated();
    }

    public function test_capturing_again_replaces_the_previous_record(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $shipmentId = $this->createShipment($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/v1/shipments/items/{$shipmentId}/proof-of-delivery", [
                'received_by_name' => 'First Receiver',
                'signature' => UploadedFile::fake()->image('signature1.png'),
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/v1/shipments/items/{$shipmentId}/proof-of-delivery", [
                'received_by_name' => 'Second Receiver',
                'signature' => UploadedFile::fake()->image('signature2.png'),
            ])->assertCreated();

        $this->assertDatabaseCount('proof_of_deliveries', 1);
        $this->assertDatabaseHas('proof_of_deliveries', ['received_by_name' => 'Second Receiver']);
    }

    public function test_portal_user_can_view_their_own_shipments_proof_of_delivery_but_not_anothers(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $customerAId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', ['company_name' => 'Customer A', 'status' => 'active'])
            ->json('data.id');
        $customerBId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', ['company_name' => 'Customer B', 'status' => 'active'])
            ->json('data.id');

        $shipmentAId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', ['customer_id' => $customerAId, 'direction' => 'import', 'mode' => 'sea'])
            ->json('data.id');
        $shipmentBId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', ['customer_id' => $customerBId, 'direction' => 'import', 'mode' => 'sea'])
            ->json('data.id');

        foreach ([$shipmentAId, $shipmentBId] as $shipmentId) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->post("/api/v1/shipments/items/{$shipmentId}/proof-of-delivery", [
                    'received_by_name' => 'Receiver',
                    'signature' => UploadedFile::fake()->image('signature.png'),
                ])->assertCreated();
        }

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Portal A',
                'email' => 'portal-a@example.test',
                'customer_id' => $customerAId,
                'role' => 'Customer Portal User',
                'password' => 'PortalPass123',
            ])->assertCreated();

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        $portalToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'portal-a@example.test',
            'password' => 'PortalPass123',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$portalToken}")
            ->getJson("/api/v1/portal/shipments/{$shipmentAId}/proof-of-delivery")
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$portalToken}")
            ->getJson("/api/v1/portal/shipments/{$shipmentBId}/proof-of-delivery")
            ->assertNotFound();
    }
}
