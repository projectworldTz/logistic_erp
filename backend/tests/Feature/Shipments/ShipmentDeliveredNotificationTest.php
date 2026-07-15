<?php

namespace Tests\Feature\Shipments;

use App\Contracts\Notifications\SmsChannel;
use App\Mail\GenericNotificationMail;
use App\Models\UserNotification;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ShipmentDeliveredNotificationTest extends TestCase
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

    private function createCustomer(string $token, string $email = 'ops@shipperco.test', ?string $phone = null): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', [
                'company_name' => 'Shipper Co',
                'email' => $email,
                'phone' => $phone,
                'status' => 'active',
            ]);

        return $response->json('data.id');
    }

    private function createShipment(string $token, int $customerId, array $overrides = []): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shipments/items', array_merge([
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
            ], $overrides));

        return $response->json('data.id');
    }

    public function test_marking_a_shipment_delivered_emails_the_customer(): void
    {
        Mail::fake();

        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token, 'ops@shipperco.test');
        $shipmentId = $this->createShipment($token, $customerId);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/shipments/items/{$shipmentId}", ['status' => 'delivered'])
            ->assertOk();

        Mail::assertSent(GenericNotificationMail::class, fn ($mail) => $mail->hasTo('ops@shipperco.test'));
    }

    public function test_marking_a_shipment_delivered_creates_an_in_app_notification_for_the_portal_user(): void
    {
        Mail::fake();

        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token, 'ops@shipperco.test');
        $shipmentId = $this->createShipment($token, $customerId);

        $portalUser = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Portal User',
                'email' => 'portal@shipperco.test',
                'customer_id' => $customerId,
                'role' => 'Customer Portal User',
                'password' => 'PortalPass123',
            ])->json('data');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/shipments/items/{$shipmentId}", ['status' => 'delivered'])
            ->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $portalUser['id'],
            'type' => 'shipment.delivered',
        ]);
    }

    public function test_updating_a_shipment_without_changing_status_to_delivered_does_not_notify(): void
    {
        Mail::fake();

        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token, 'ops@shipperco.test');
        $shipmentId = $this->createShipment($token, $customerId);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/shipments/items/{$shipmentId}", ['status' => 'in_transit'])
            ->assertOk();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('user_notifications', 0);
    }

    public function test_delivered_notification_email_is_suppressed_when_tenant_disables_email(): void
    {
        Mail::fake();

        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token, 'ops@shipperco.test');
        $shipmentId = $this->createShipment($token, $customerId);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/company', ['notify_email_enabled' => false])
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/shipments/items/{$shipmentId}", ['status' => 'delivered'])
            ->assertOk();

        Mail::assertNothingSent();
    }

    public function test_marking_a_shipment_delivered_texts_the_customer_when_sms_is_enabled(): void
    {
        Mail::fake();
        $this->mock(SmsChannel::class)
            ->shouldReceive('send')
            ->once()
            ->with('+255712345678', \Mockery::type('string'));

        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token, 'ops@shipperco.test', '+255712345678');
        $shipmentId = $this->createShipment($token, $customerId);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/company', ['notify_sms_enabled' => true])
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/shipments/items/{$shipmentId}", ['status' => 'delivered'])
            ->assertOk();
    }

    public function test_delivered_message_includes_the_customers_company_name_and_cargo_description(): void
    {
        Mail::fake();

        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token, 'ops@shipperco.test');

        $clearingFileId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/clearing/files', [
                'customer_id' => $customerId,
                'direction' => 'import',
                'mode' => 'sea',
                'cargo_description' => '20 pallets of ceramic tiles',
            ])->json('data.id');

        $shipmentId = $this->createShipment($token, $customerId, ['clearing_file_id' => $clearingFileId]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/shipments/items/{$shipmentId}", ['status' => 'delivered'])
            ->assertOk();

        Mail::assertSent(GenericNotificationMail::class, fn ($mail) => str_contains($mail->body, 'Shipper Co')
            && str_contains($mail->body, '20 pallets of ceramic tiles'));
    }
}
