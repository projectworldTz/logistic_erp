<?php

namespace Tests\Feature\Tracking;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentTrackingQrTest extends TestCase
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

    private function invitePortalUser(string $staffToken, int $customerId, string $email): void
    {
        $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/users', [
                'name' => 'Portal User',
                'email' => $email,
                'customer_id' => $customerId,
                'role' => 'Customer Portal User',
                'password' => 'PortalPass123',
            ])->assertCreated();
    }

    private function createShipment(string $staffToken, int $customerId): array
    {
        return $this->withHeader('Authorization', "Bearer {$staffToken}")
            ->postJson('/api/v1/shipments/items', [
                'customer_id' => $customerId,
                'direction' => 'export',
                'mode' => 'air',
            ])->json('data');
    }

    private function portalLogin(string $email): string
    {
        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'PortalPass123',
        ]);

        return $response->json('token');
    }

    public function test_staff_can_fetch_an_svg_tracking_qr_code_for_a_shipment(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token, 'Shipper Co');
        $shipment = $this->createShipment($token, $customerId);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/shipments/items/{$shipment['id']}/tracking-qr");

        $response->assertOk();
        $response->assertHeader('content-type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $response->getContent());
        $this->assertGreaterThan(100, strlen($response->getContent()));

        $expectedUrl = app(\App\Services\Tracking\ShipmentTrackingQrService::class)
            ->trackingUrl($shipment['tracking_code']);
        $this->assertStringContainsString($shipment['tracking_code'], $expectedUrl);
        $this->assertStringStartsWith(config('app.frontend_url'), $expectedUrl);
    }

    public function test_portal_user_can_fetch_qr_for_their_own_shipment_but_not_anothers(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $customerA = $this->createCustomer($token, 'Shipper Co');
        $this->invitePortalUser($token, $customerA, 'portal@shipperco.test');
        $shipmentA = $this->createShipment($token, $customerA);

        $customerB = $this->createCustomer($token, 'Other Shipper');
        $shipmentB = $this->createShipment($token, $customerB);

        $portalToken = $this->portalLogin('portal@shipperco.test');

        $this->withHeader('Authorization', "Bearer {$portalToken}")
            ->get("/api/v1/portal/shipments/{$shipmentA['id']}/tracking-qr")
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml');

        $this->withHeader('Authorization', "Bearer {$portalToken}")
            ->get("/api/v1/portal/shipments/{$shipmentB['id']}/tracking-qr")
            ->assertNotFound();
    }

    public function test_invoice_pdf_renders_with_a_tracking_qr_when_linked_to_a_shipment(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token, 'Shipper Co');
        $shipment = $this->createShipment($token, $customerId);

        $invoice = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'shipment_id' => $shipment['id'],
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 1000,
                'total_amount' => 1000,
            ])->json('data');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/finance/invoices/{$invoice['id']}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_invoice_pdf_still_renders_without_a_linked_shipment(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $customerId = $this->createCustomer($token, 'Shipper Co');

        $invoice = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/invoices', [
                'customer_id' => $customerId,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => 500,
                'total_amount' => 500,
            ])->json('data');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/v1/finance/invoices/{$invoice['id']}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
