<?php

namespace Tests\Feature\Platform;

use Database\Seeders\LandingContentSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandingContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
        $this->seed(SuperAdminSeeder::class);
        $this->seed(LandingContentSeeder::class);
    }

    private function registerTenant(string $email = 'jane@acme.test'): array
    {
        $response = $this->postJson('/api/v1/tenants/register', [
            'plan_code' => 'starter',
            'owner' => ['name' => 'Jane Doe', 'email' => $email, 'password' => 'SecurePass123'],
            'company' => [
                'name' => 'Acme Logistics',
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

    public function test_super_admin_can_list_and_update_a_section(): void
    {
        $adminToken = $this->superAdminToken();

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/v1/platform/landing-content')
            ->assertOk()
            ->assertJsonCount(6, 'data');

        $content = config('landing_content.hero');
        $content['headline'] = 'A brand new headline';

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson('/api/v1/platform/landing-content/hero', ['content' => $content])
            ->assertOk()
            ->assertJsonPath('data.content.headline', 'A brand new headline');

        $this->assertDatabaseHas('audit_logs', ['action' => 'landing_content.updated']);
    }

    public function test_update_rejects_payload_missing_required_keys(): void
    {
        $adminToken = $this->superAdminToken();

        $content = config('landing_content.hero');
        unset($content['headline']);

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson('/api/v1/platform/landing-content/hero', ['content' => $content])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_tenant_user_cannot_access_platform_landing_content_routes(): void
    {
        $registration = $this->registerTenant();
        $tenantToken = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$tenantToken}")
            ->getJson('/api/v1/platform/landing-content')
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$tenantToken}")
            ->putJson('/api/v1/platform/landing-content/hero', ['content' => config('landing_content.hero')])
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$tenantToken}")
            ->postJson('/api/v1/platform/landing-content/upload-image')
            ->assertForbidden();
    }

    public function test_super_admin_can_upload_landing_image(): void
    {
        Storage::fake('public');
        $adminToken = $this->superAdminToken();

        $file = UploadedFile::fake()->image('hero.jpg', 1200, 800);

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->post('/api/v1/platform/landing-content/upload-image', [
                'image' => $file,
                'purpose' => 'hero',
            ])
            ->assertOk()
            ->assertJsonStructure(['url']);
    }
}
