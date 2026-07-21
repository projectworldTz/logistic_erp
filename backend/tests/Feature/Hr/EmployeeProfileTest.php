<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeeProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
        Storage::fake('local');
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

    private function createDesignation(string $token, array $overrides = []): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/designations', array_merge([
                'name' => 'Clearing Officer',
                'category' => 'clearing_and_customs',
            ], $overrides))
            ->json('data.id');
    }

    private function createEmployee(string $token, array $overrides = []): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', array_merge([
                'first_name' => 'John',
                'last_name' => 'Kamau',
                'job_title' => 'Warehouse Clerk',
                'hire_date' => now()->subYear()->toDateString(),
                'salary' => 1200,
            ], $overrides))
            ->json('data.id');
    }

    /** A second tenant user assigned "HR Officer" — has hr.employees.manage but deliberately not salary.view/contracts.approve. */
    private function actingAsHrOfficer(int $tenantId): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $officer = User::factory()->create(['tenant_id' => $tenantId]);
        $officer->assignRole('HR Officer');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($officer, ['*']);
        app(TenantContext::class)->set($tenantId);

        return $officer;
    }

    public function test_owner_can_create_and_list_designations(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/designations', ['name' => 'Fleet Manager', 'category' => 'transport_and_fleet']);

        $create->assertCreated();
        $create->assertJsonPath('data.name', 'Fleet Manager');
        $create->assertJsonPath('data.category', 'transport_and_fleet');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/hr/designations')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Fleet Manager']);
    }

    public function test_duplicate_designation_name_within_tenant_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $this->createDesignation($token);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/designations', ['name' => 'Clearing Officer'])
            ->assertUnprocessable();
    }

    public function test_employee_created_from_first_and_last_name_derives_full_name(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $designationId = $this->createDesignation($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', [
                'first_name' => 'Amina',
                'last_name' => 'Hassan',
                'designation_id' => $designationId,
                'hire_date' => now()->subMonths(2)->toDateString(),
                'statutory_details' => ['tin' => '123-456-789'],
            ]);

        $create->assertCreated();
        $create->assertJsonPath('data.name', 'Amina Hassan');
        $create->assertJsonPath('data.first_name', 'Amina');
        $create->assertJsonPath('data.designation.name', 'Clearing Officer');
        $create->assertJsonPath('data.statutory_details.tin', '123-456-789');

        // Salary must never appear on the general employee resource.
        $create->assertJsonMissingPath('data.salary');
        $create->assertJsonMissingPath('data.bank_account_number');
    }

    public function test_salary_is_only_visible_through_the_dedicated_permission_and_endpoint(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];
        $employeeId = $this->createEmployee($token, ['salary' => 1500, 'bank_name' => 'Equity Bank']);

        // Owner (hr.* wildcard, includes salary.view) can see it.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/hr/employees/{$employeeId}/salary")
            ->assertOk()
            ->assertJsonPath('data.salary', '1500.00')
            ->assertJsonPath('data.bank_name', 'Equity Bank');

        // HR Officer has hr.employees.manage but not hr.employees.salary.view.
        $this->actingAsHrOfficer($tenantId);

        $this->getJson("/api/v1/hr/employees/{$employeeId}")->assertOk();
        $this->getJson("/api/v1/hr/employees/{$employeeId}/salary")->assertForbidden();
    }

    public function test_employee_document_can_be_uploaded_verified_and_downloaded_via_signed_url(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);

        $file = UploadedFile::fake()->create('national-id.pdf', 100, 'application/pdf');

        $upload = $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/v1/hr/employees/{$employeeId}/documents", [
                'document_type' => 'national_id',
                'file' => $file,
                'expiry_date' => now()->addYears(5)->toDateString(),
            ]);

        $upload->assertCreated();
        $upload->assertJsonPath('data.document_type', 'national_id');
        $upload->assertJsonPath('data.status', 'pending_verification');
        $documentId = $upload->json('data.id');
        $downloadUrl = $upload->json('data.download_url');
        $this->assertNotNull($downloadUrl);

        $storedPath = \App\Models\EmployeeDocument::findOrFail($documentId)->file_path;
        Storage::disk('local')->assertExists($storedPath);

        // The signed URL works standalone, without a bearer token.
        $this->get($downloadUrl)->assertOk();

        $verify = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/employee-documents/{$documentId}/verify");
        $verify->assertOk();
        $verify->assertJsonPath('data.status', 'valid');

        $this->assertDatabaseHas('audit_logs', ['action' => 'employee_document.verified']);
    }

    public function test_employee_document_rejection_requires_a_reason(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);

        $file = UploadedFile::fake()->create('cert.pdf', 50, 'application/pdf');
        $documentId = $this->withHeader('Authorization', "Bearer {$token}")
            ->post("/api/v1/hr/employees/{$employeeId}/documents", [
                'document_type' => 'training_certificate',
                'file' => $file,
            ])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/employee-documents/{$documentId}/reject", [])
            ->assertUnprocessable();

        $reject = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/employee-documents/{$documentId}/reject", ['reason' => 'Illegible scan']);

        $reject->assertOk();
        $reject->assertJsonPath('data.status', 'rejected');
    }

    public function test_employee_contract_lifecycle_submit_approve(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/contracts', [
                'employee_id' => $employeeId,
                'employment_type' => 'permanent',
                'effective_date' => now()->toDateString(),
                'basic_salary' => 2000,
                'pay_frequency' => 'monthly',
                'overtime_eligible' => true,
            ]);

        $create->assertCreated();
        $contractId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'draft');
        $this->assertStringStartsWith('CON-', $create->json('data.contract_number'));

        $submit = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/contracts/{$contractId}/submit");
        $submit->assertOk();
        $submit->assertJsonPath('data.status', 'pending_approval');

        // No ApprovalWorkflow is configured for this tenant, so approve()
        // falls back to a direct hr.contracts.approve permission check.
        $approve = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/contracts/{$contractId}/approve");
        $approve->assertOk();
        $approve->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('audit_logs', ['action' => 'employee_contract.approved']);
    }

    public function test_hr_officer_cannot_approve_a_contract(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];
        $employeeId = $this->createEmployee($token);

        $contractId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/contracts', [
                'employee_id' => $employeeId,
                'employment_type' => 'permanent',
                'effective_date' => now()->toDateString(),
                'basic_salary' => 2000,
            ])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/contracts/{$contractId}/submit")
            ->assertOk();

        $this->actingAsHrOfficer($tenantId);

        // HR Officer can view/manage contracts but not approve them.
        $this->getJson("/api/v1/hr/contracts/{$contractId}")->assertOk();
        $this->postJson("/api/v1/hr/contracts/{$contractId}/approve")->assertForbidden();
    }

    public function test_hr_records_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $this->createDesignation($registrationA['token']);
        $employeeIdA = $this->createEmployee($registrationA['token']);
        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/hr/contracts', [
                'employee_id' => $employeeIdA,
                'employment_type' => 'permanent',
                'effective_date' => now()->toDateString(),
                'basic_salary' => 1000,
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();

        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/hr/designations')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/contracts')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/hr/employees/{$employeeIdA}")->assertNotFound();
    }
}
