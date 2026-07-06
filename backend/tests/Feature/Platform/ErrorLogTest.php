<?php

namespace Tests\Feature\Platform;

use App\Models\ErrorLog;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
        $this->seed(SuperAdminSeeder::class);
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

    private function superAdminToken(): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => env('SUPER_ADMIN_EMAIL', 'admin@example.com'),
            'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
        ]);

        return $response->json('token');
    }

    public function test_uncaught_exception_returns_reference_code_and_creates_error_log(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];
        $userId = $registration['user']['id'];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/_test/throw');

        $response->assertStatus(500);
        $response->assertJsonStructure(['message', 'reference']);
        $reference = $response->json('reference');
        $this->assertNotSame('UNLOGGED', $reference);

        $this->assertDatabaseHas('error_logs', [
            'reference' => $reference,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'exception_class' => \RuntimeException::class,
            'message' => 'Deliberate test exception',
            'status_code' => 500,
        ]);
    }

    public function test_validation_error_is_unaffected_and_creates_no_error_log(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/quotations/items', [])
            ->assertStatus(422);

        $this->assertDatabaseCount('error_logs', 0);
    }

    public function test_permission_denied_is_unaffected_and_creates_no_error_log(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/platform/tenants')
            ->assertForbidden();

        $this->assertDatabaseCount('error_logs', 0);
    }

    public function test_super_admin_can_list_filter_and_search_error_logs(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $registrationB = $this->registerTenant('bob@globex.test', 'Globex Freight');

        // Tenant A's log is created via a real request (proves the HTTP pipeline
        // works, already covered in detail by test_uncaught_exception_...).
        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")->getJson('/api/v1/_test/throw');

        // Tenant B's log is inserted directly rather than via a second raw
        // bearer-token request in this same test: Sanctum's RequestGuard caches
        // the first resolved user, so a second bearer token in one test method
        // would incorrectly resolve back to tenant A's user (documented gotcha).
        // This test only needs two known tenant_ids in the table to prove
        // listing/filtering works — not a second live HTTP round trip.
        ErrorLog::create([
            'reference' => 'TESTREF2',
            'tenant_id' => $registrationB['user']['tenant_id'],
            'user_id' => $registrationB['user']['id'],
            'exception_class' => \RuntimeException::class,
            'message' => 'Second tenant test exception',
            'status_code' => 500,
        ]);

        // auth:sanctum permanently swaps the default guard on every successful bearer
        // auth — reset it before the login endpoint's Auth::attempt() call below.
        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        // TenantContext is a singleton that isn't reset between simulated requests
        // within one test method — it's still scoped to tenant A from the earlier
        // _test/throw call, which would silently hide the super admin (tenant_id
        // null) from User::where('email', ...) during login. Clear it first.
        app(\App\Support\Tenancy\TenantContext::class)->clear();

        $adminToken = $this->superAdminToken();

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/v1/platform/error-logs')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/v1/platform/error-logs?tenant_id=' . $registrationA['user']['tenant_id'])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $errorLog = ErrorLog::where('reference', 'TESTREF2')->firstOrFail();

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/v1/platform/error-logs?q=' . $errorLog->reference)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $errorLog->reference);
    }

    public function test_tenant_user_cannot_access_error_log_routes(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/platform/error-logs')
            ->assertForbidden();
    }

    public function test_super_admin_can_view_detail_and_resolve_an_error_log(): void
    {
        $registration = $this->registerTenant();
        $this->withHeader('Authorization', "Bearer {$registration['token']}")->getJson('/api/v1/_test/throw');

        $errorLog = ErrorLog::firstOrFail();

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();
        app(\App\Support\Tenancy\TenantContext::class)->clear();

        $adminToken = $this->superAdminToken();

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson("/api/v1/platform/error-logs/{$errorLog->id}")
            ->assertOk()
            ->assertJsonPath('data.exception_class', \RuntimeException::class)
            ->assertJsonPath('data.resolved_at', null);

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson("/api/v1/platform/error-logs/{$errorLog->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.resolved_at', fn ($value) => $value !== null);

        $this->assertNotNull($errorLog->fresh()->resolved_at);
    }

    public function test_sensitive_request_fields_are_redacted_in_persisted_payload(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/_test/throw', ['password' => 'super-secret-value', 'notes' => 'keep me']);

        $errorLog = ErrorLog::latest('id')->firstOrFail();

        $this->assertSame('[REDACTED]', $errorLog->request_payload['password']);
        $this->assertSame('keep me', $errorLog->request_payload['notes']);
    }
}
