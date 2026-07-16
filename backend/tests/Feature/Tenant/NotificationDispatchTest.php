<?php

namespace Tests\Feature\Tenant;

use App\Contracts\Notifications\SmsChannel;
use App\Contracts\Notifications\WhatsAppChannel;
use App\Mail\GenericNotificationMail;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
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
                'email' => 'ops@shipperco.test',
                'status' => 'active',
            ]);

        return $response->json('data.id');
    }

    private function createRecipient(int $tenantId, array $overrides = []): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $recipient = User::factory()->create(array_merge([
            'tenant_id' => $tenantId,
            'email' => 'recipient@acme.test',
        ], $overrides));
        $recipient->assignRole('Clearing Officer');

        return $recipient;
    }

    private function createContainer(string $token, int $customerId): void
    {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/containers/items', [
                'customer_id' => $customerId,
                'container_number' => 'MSCU1234567',
                'container_type' => 'dry_40',
            ])->assertCreated();
    }

    public function test_email_notification_is_sent_by_default_when_a_module_event_fires(): void
    {
        Mail::fake();

        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $recipient = $this->createRecipient($tenantId);

        $this->createContainer($token, $customerId);

        Mail::assertSent(GenericNotificationMail::class, fn ($mail) => $mail->hasTo($recipient->email));
    }

    public function test_email_is_not_sent_when_the_tenant_disables_it(): void
    {
        Mail::fake();

        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRecipient($tenantId);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/company', ['notify_email_enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.notify_email_enabled', false);

        $this->createContainer($token, $customerId);

        Mail::assertNothingSent();
    }

    public function test_sms_channel_is_invoked_when_enabled_and_recipient_has_a_phone(): void
    {
        Mail::fake();
        $this->mock(SmsChannel::class)
            ->shouldReceive('send')
            ->once()
            ->with('+254700000000', \Mockery::type('string'));

        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRecipient($tenantId, ['phone' => '+254700000000']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/company', ['notify_sms_enabled' => true])
            ->assertOk();

        $this->createContainer($token, $customerId);
    }

    public function test_whatsapp_channel_is_invoked_when_enabled_and_recipient_has_a_phone(): void
    {
        Mail::fake();
        $this->mock(WhatsAppChannel::class)
            ->shouldReceive('send')
            ->once()
            ->with('+254700000000', \Mockery::type('string'));

        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRecipient($tenantId, ['phone' => '+254700000000']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/company', ['notify_whatsapp_enabled' => true])
            ->assertOk();

        $this->createContainer($token, $customerId);
    }

    public function test_email_is_branded_with_company_logo_color_and_footer_text(): void
    {
        Mail::fake();

        $registration = $this->registerTenant(companyName: 'Acme Logistics');
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRecipient($tenantId);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/company', [
                'primary_color' => '#123456',
                'email_footer_text' => 'Acme Logistics — Nairobi, Kenya',
                'email_reply_to' => 'support@acmelogistics.test',
            ])
            ->assertOk();

        $this->createContainer($token, $customerId);

        Mail::assertSent(GenericNotificationMail::class, function ($mail) {
            $html = $mail->render();

            return str_contains($html, 'Acme Logistics')
                && str_contains($html, '#123456')
                && str_contains($html, 'Acme Logistics — Nairobi, Kenya')
                && $mail->envelope()->replyTo[0]->address === 'support@acmelogistics.test';
        });
    }

    public function test_sms_is_not_attempted_when_recipient_has_no_phone_even_if_enabled(): void
    {
        Mail::fake();
        $this->mock(SmsChannel::class)->shouldNotReceive('send');

        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];
        $customerId = $this->createCustomer($token);
        $this->createRecipient($tenantId, ['phone' => null]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/company', ['notify_sms_enabled' => true])
            ->assertOk();

        $this->createContainer($token, $customerId);
    }
}
