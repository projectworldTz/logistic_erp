<?php

namespace Tests\Feature\Platform;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
        $this->seed(SuperAdminSeeder::class);
    }

    private function superAdminToken(): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => env('SUPER_ADMIN_EMAIL', 'admin@example.com'),
            'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
        ]);

        return $response->json('token');
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

    public function test_super_admin_can_view_system_health(): void
    {
        $adminToken = $this->superAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/v1/platform/system-health');

        $response->assertOk();
        $response->assertJsonPath('database.status', 'ok');
        $response->assertJsonPath('cache.status', 'ok');
        $response->assertJsonPath('queue.status', 'ok');
        $response->assertJsonStructure([
            'database' => ['status', 'response_ms'],
            'cache' => ['status', 'driver'],
            'queue' => ['status', 'pending_jobs', 'failed_jobs'],
            'storage' => ['status'],
            'app' => ['environment', 'php_version', 'laravel_version', 'debug_mode', 'mailer', 'queue_connection'],
        ]);
    }

    public function test_tenant_user_cannot_access_system_health(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->getJson('/api/v1/platform/system-health')
            ->assertForbidden();
    }
}
