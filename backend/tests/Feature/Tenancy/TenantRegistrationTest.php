<?php

namespace Tests\Feature\Tenancy;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BillingProfile;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantDashboardSetting;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'plan_code' => 'starter',
            'owner' => [
                'name' => 'Jane Doe',
                'email' => 'jane@acme.test',
                'phone' => '+15551234567',
                'password' => 'SecurePass123',
            ],
            'company' => [
                'name' => 'Acme Logistics',
                'country' => 'Kenya',
                'city' => 'Nairobi',
                'address' => '123 Port Rd',
                'currency' => 'USD',
                'timezone' => 'Africa/Nairobi',
                'industry' => 'Freight Forwarding',
            ],
        ], $overrides);
    }

    public function test_registration_atomically_creates_every_related_record(): void
    {
        $response = $this->postJson('/api/v1/tenants/register', $this->payload());

        $response->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'tenant_id', 'roles', 'permissions']]);

        $this->assertSame(1, Tenant::count());
        $this->assertSame(1, Company::count());
        $this->assertSame(1, Branch::count());
        $this->assertSame(1, Subscription::count());
        $this->assertSame(1, BillingProfile::count());
        $this->assertSame(1, TenantDashboardSetting::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.provisioned']);

        $owner = User::where('email', 'jane@acme.test')->firstOrFail();
        $this->assertTrue($owner->hasRole('Company Owner'));
        $this->assertTrue($owner->can('core.dashboard.view'));

        $branch = Branch::firstOrFail();
        $this->assertTrue($branch->is_default);
        $this->assertSame($owner->tenant_id, $branch->tenant_id);
    }

    public function test_registration_rejects_duplicate_owner_email_without_creating_a_second_tenant(): void
    {
        $this->postJson('/api/v1/tenants/register', $this->payload())->assertCreated();
        $this->assertSame(1, Tenant::count());

        $response = $this->postJson('/api/v1/tenants/register', $this->payload([
            'company' => ['name' => 'Different Co'],
        ]));

        $response->assertStatus(422);
        $this->assertSame(1, Tenant::count());
        $this->assertSame(1, User::count());
    }

    public function test_registration_requires_a_valid_plan_code(): void
    {
        $response = $this->postJson('/api/v1/tenants/register', $this->payload(['plan_code' => 'not-a-real-plan']));

        $response->assertStatus(422);
        $this->assertSame(0, Tenant::count());
    }
}
