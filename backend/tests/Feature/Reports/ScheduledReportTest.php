<?php

namespace Tests\Feature\Reports;

use App\Mail\ScheduledReportMail;
use App\Models\ScheduledReport;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduledReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function registerTenant(string $email = 'owner@acme.test', string $companyName = 'Acme Logistics'): array
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

    private function createCustomer(string $token, string $companyName): int
    {
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/crm/customers', [
                'company_name' => $companyName,
                'email' => strtolower(str_replace(' ', '', $companyName)).'@customer.test',
                'status' => 'active',
            ]);

        return $response->json('data.id');
    }

    public function test_owner_can_create_list_and_delete_a_scheduled_report(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/reports/scheduled', [
                'name' => 'Weekly Customers',
                'module' => 'customers',
                'format' => 'csv',
                'frequency' => 'weekly',
                'recipients' => ['ops@acme.test'],
            ]);

        $created->assertCreated();
        $this->assertEquals('customers', $created->json('data.module'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/scheduled')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/reports/scheduled/{$created->json('data.id')}")
            ->assertNoContent();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/scheduled')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_without_manage_permission_cannot_create_a_scheduled_report(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'No Access',
                'email' => 'noaccess@acme.test',
                'roles' => ['Driver'],
                'password' => 'SecurePass123',
            ])->assertCreated();

        app(TenantContext::class)->clear();
        $restrictedUser = User::where('email', 'noaccess@acme.test')->firstOrFail();
        Sanctum::actingAs($restrictedUser, ['*']);

        $this->postJson('/api/v1/reports/scheduled', [
            'name' => 'Weekly Customers',
            'module' => 'customers',
            'format' => 'csv',
            'frequency' => 'weekly',
            'recipients' => ['ops@acme.test'],
        ])->assertForbidden();
    }

    public function test_command_emails_due_reports_and_updates_last_sent_at(): void
    {
        Mail::fake();

        $registration = $this->registerTenant();
        $token = $registration['token'];
        $this->createCustomer($token, 'Shipper Co');

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/reports/scheduled', [
                'name' => 'Weekly Customers',
                'module' => 'customers',
                'format' => 'csv',
                'frequency' => 'weekly',
                'recipients' => ['ops@acme.test'],
            ])->json('data');

        Artisan::call('reports:send-scheduled');

        Mail::assertSent(ScheduledReportMail::class, fn ($mail) => $mail->hasTo('ops@acme.test')
            && $mail->reportName === 'Weekly Customers'
            && str_contains($mail->fileContent, 'Shipper Co'));

        app(TenantContext::class)->clear();
        $report = ScheduledReport::find($created['id']);
        $this->assertNotNull($report->last_sent_at);
    }

    public function test_command_does_not_resend_a_report_that_is_not_yet_due(): void
    {
        Mail::fake();

        $registration = $this->registerTenant();
        $token = $registration['token'];
        $this->createCustomer($token, 'Shipper Co');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/reports/scheduled', [
                'name' => 'Weekly Customers',
                'module' => 'customers',
                'format' => 'csv',
                'frequency' => 'weekly',
                'recipients' => ['ops@acme.test'],
            ])->assertCreated();

        Artisan::call('reports:send-scheduled');
        Artisan::call('reports:send-scheduled');

        Mail::assertSent(ScheduledReportMail::class, 1);
    }

    public function test_inactive_scheduled_report_is_never_sent(): void
    {
        Mail::fake();

        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/reports/scheduled', [
                'name' => 'Weekly Customers',
                'module' => 'customers',
                'format' => 'csv',
                'frequency' => 'weekly',
                'recipients' => ['ops@acme.test'],
                'is_active' => false,
            ])->assertCreated();

        Artisan::call('reports:send-scheduled');

        Mail::assertNothingSent();
    }
}
