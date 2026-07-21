<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataImportTest extends TestCase
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

    private function csvFile(string $contents, string $name = 'import.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    public function test_owner_can_bulk_import_customers_from_csv(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $csv = "Company Name,Industry,Email,Phone,City,Country,Status\n"
            ."Shipper Co,Retail,ops@shipperco.test,+254700000000,Nairobi,Kenya,active\n"
            ."Second Shipper,Manufacturing,,,Mombasa,Kenya,inactive\n";

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Accept', 'application/json')
            ->post('/api/v1/reports/import/customers', ['file' => $this->csvFile($csv)]);

        $response->assertOk();
        $this->assertEquals(2, $response->json('created'));
        $this->assertEmpty($response->json('errors'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/crm/customers')
            ->assertJsonFragment(['company_name' => 'Shipper Co'])
            ->assertJsonFragment(['company_name' => 'Second Shipper']);
    }

    public function test_invalid_rows_are_reported_without_blocking_valid_ones(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $csv = "Company Name,Industry,Email,Phone,City,Country,Status\n"
            .",Retail,ops@shipperco.test,,Nairobi,Kenya,active\n" // missing company_name
            ."Valid Co,Retail,,,,,active\n";

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Accept', 'application/json')
            ->post('/api/v1/reports/import/customers', ['file' => $this->csvFile($csv)]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('created'));
        $this->assertCount(1, $response->json('errors'));
        $this->assertEquals(2, $response->json('errors.0.row'));
    }

    public function test_owner_can_bulk_import_leads_from_csv(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $csv = "Company Name,Contact Name,Email,Phone,Source,Status\n"
            ."Prospect Co,John Doe,john@prospect.test,+254711111111,website,new\n";

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Accept', 'application/json')
            ->post('/api/v1/reports/import/leads', ['file' => $this->csvFile($csv)]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('created'));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/crm/leads')
            ->assertJsonFragment(['company_name' => 'Prospect Co']);
    }

    public function test_user_without_manage_permission_cannot_import(): void
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

        $csv = "Company Name\nShipper Co\n";

        $this->withHeader('Accept', 'application/json')
            ->post('/api/v1/reports/import/customers', ['file' => $this->csvFile($csv)])
            ->assertForbidden();
    }
}
