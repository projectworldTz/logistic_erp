<?php

namespace Tests\Feature\Tenant;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyBrandingTest extends TestCase
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

    public function test_owner_can_set_brand_colors(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/company', [
                'primary_color' => '#FF5733',
                'secondary_color' => '#33FF57',
            ])
            ->assertOk()
            ->assertJsonPath('data.primary_color', '#FF5733')
            ->assertJsonPath('data.secondary_color', '#33FF57');
    }

    public function test_invalid_hex_color_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/company', ['primary_color' => 'not-a-color'])
            ->assertUnprocessable();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/company', ['primary_color' => '#FFF'])
            ->assertUnprocessable();
    }

    public function test_owner_can_upload_and_replace_a_company_logo(): void
    {
        Storage::fake('public');
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $firstLogo = UploadedFile::fake()->image('logo.jpg', 800, 800);

        $firstUpload = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/company/logo', ['logo' => $firstLogo]);

        $firstUpload->assertOk();
        $firstUrl = $firstUpload->json('data.logo_url');
        $this->assertNotNull($firstUrl);

        $firstPath = $this->extractStoragePath($firstUrl);
        Storage::disk('public')->assertExists($firstPath);

        $secondLogo = UploadedFile::fake()->image('logo2.png', 600, 600);

        $secondUpload = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/company/logo', ['logo' => $secondLogo]);

        $secondUpload->assertOk();
        $secondUrl = $secondUpload->json('data.logo_url');
        $this->assertNotEquals($firstUrl, $secondUrl);

        // The old logo file is deleted once replaced.
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($this->extractStoragePath($secondUrl));
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Accept', 'application/json')
            ->post('/api/v1/company/logo', ['logo' => $file])
            ->assertUnprocessable();
    }

    private function extractStoragePath(string $url): string
    {
        return \Illuminate\Support\Str::after(parse_url($url, PHP_URL_PATH), '/storage/');
    }
}
