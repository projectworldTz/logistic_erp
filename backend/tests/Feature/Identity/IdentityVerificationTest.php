<?php

namespace Tests\Feature\Identity;

use App\Enums\IdentityVerificationStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeIdentityVerification;
use App\Models\User;
use App\Services\Identity\IdentityProviderFactory;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFIED_NUMBER = '199206121234500001';

    private const NOT_FOUND_NUMBER = '000000000000000001';

    private const INACTIVE_NUMBER = '000000000000000002';

    private const EXPIRED_NUMBER = '000000000000000003';

    private const PROVIDER_UNAVAILABLE_NUMBER = '000000000000000004';

    private const MISMATCH_NUMBER = '000000000000000005';

    private const RATE_LIMITED_NUMBER = '000000000000000006';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
        Storage::fake('local');
        RateLimiter::clear('identity-verify:1');
    }

    private function registerTenant(string $email = 'jane@acme.test'): array
    {
        $response = $this->postJson('/api/v1/tenants/register', [
            'plan_code' => 'starter',
            'owner' => ['name' => 'Jane Doe', 'email' => $email, 'password' => 'SecurePass123'],
            'company' => [
                'name' => 'Acme Logistics',
                'country' => 'Tanzania',
                'city' => 'Dar es Salaam',
                'address' => '123 Port Rd',
                'currency' => 'TZS',
                'timezone' => 'Africa/Nairobi',
                'industry' => 'Freight Forwarding',
            ],
        ]);

        return $response->json();
    }

    private function actingAsRole(int $tenantId, string $role): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $user->assignRole($role);

        app(TenantContext::class)->clear();
        Sanctum::actingAs($user, ['*']);
        app(TenantContext::class)->set($tenantId);

        return $user;
    }

    private function verifyPayload(string $number = self::VERIFIED_NUMBER): array
    {
        return [
            'document_type' => 'national_id',
            'identity_number' => $number,
            'country_code' => 'TZ',
        ];
    }

    // --- Successful verification -------------------------------------------------

    public function test_successful_mock_verification_returns_verified_person_and_document(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $this->actingAsRole($tenantId, 'HR Officer');

        $response = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload());

        $response->assertCreated()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.status', 'verified')
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.person.first_name', 'John')
            ->assertJsonPath('data.person.last_name', 'Mrema')
            ->assertJsonPath('data.document.status', 'active');

        $this->assertStringNotContainsString(self::VERIFIED_NUMBER, $response->getContent());
    }

    // --- Failure scenarios ---------------------------------------------------------

    public function test_identity_not_found(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $response = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload(self::NOT_FOUND_NUMBER));

        $response->assertCreated()
            ->assertJsonPath('data.verified', false)
            ->assertJsonPath('data.status', 'not_found');
    }

    public function test_inactive_identity(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $response = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload(self::INACTIVE_NUMBER));

        $response->assertCreated()->assertJsonPath('data.status', 'inactive');
    }

    public function test_expired_identity(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $response = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload(self::EXPIRED_NUMBER));

        $response->assertCreated()->assertJsonPath('data.status', 'expired');
    }

    public function test_verification_mismatch(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $response = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload(self::MISMATCH_NUMBER));

        $response->assertCreated()->assertJsonPath('data.status', 'failed');
    }

    public function test_provider_unavailable_returns_503(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $response = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload(self::PROVIDER_UNAVAILABLE_NUMBER));

        $response->assertStatus(503);
    }

    public function test_provider_level_rate_limit_returns_429(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $response = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload(self::RATE_LIMITED_NUMBER));

        $response->assertStatus(429);
    }

    public function test_invalid_identity_format_is_rejected_by_validation(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $response = $this->postJson('/api/v1/hr/identity-verifications', [
            'document_type' => 'not_a_real_type',
            'identity_number' => '',
            'country_code' => 'TZ',
        ]);

        $response->assertStatus(422);
    }

    // --- Authorization / tenant isolation -------------------------------------------

    public function test_unauthorized_user_cannot_verify_identity(): void
    {
        $registration = $this->registerTenant();
        app(PermissionRegistrar::class)->setPermissionsTeamId($registration['user']['tenant_id']);
        $noPermUser = User::factory()->create(['tenant_id' => $registration['user']['tenant_id']]);
        app(TenantContext::class)->clear();
        Sanctum::actingAs($noPermUser, ['*']);
        app(TenantContext::class)->set($registration['user']['tenant_id']);

        $response = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload());

        $response->assertForbidden();
    }

    public function test_verification_is_isolated_per_tenant(): void
    {
        $tenantA = $this->registerTenant('owner-a@acme.test');
        $userA = $this->actingAsRole($tenantA['user']['tenant_id'], 'HR Officer');

        $verificationId = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload())
            ->json('data.id');

        $tenantB = $this->registerTenant('owner-b@acme.test');
        $this->actingAsRole($tenantB['user']['tenant_id'], 'HR Officer');

        $this->getJson("/api/v1/hr/identity-verifications/{$verificationId}")->assertNotFound();
    }

    // --- Confirm / reject ------------------------------------------------------------

    public function test_successful_confirmation(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $verificationId = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload())->json('data.id');

        $response = $this->postJson("/api/v1/hr/identity-verifications/{$verificationId}/confirm");

        $response->assertOk();

        $verification = EmployeeIdentityVerification::query()->findOrFail($verificationId);
        $this->assertNotNull($verification->confirmed_at);
        $this->assertNotNull($verification->confirmed_by);
    }

    public function test_rejected_verification(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $verificationId = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload())->json('data.id');

        $response = $this->postJson("/api/v1/hr/identity-verifications/{$verificationId}/reject");

        $response->assertOk()->assertJsonPath('data.status', 'rejected');
    }

    public function test_confirming_an_unverified_result_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $verificationId = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload(self::NOT_FOUND_NUMBER))
            ->json('data.id');

        $this->postJson("/api/v1/hr/identity-verifications/{$verificationId}/confirm")->assertStatus(500);
    }

    // --- Manual review -----------------------------------------------------------

    public function test_manual_review_submission(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $verificationId = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload(self::NOT_FOUND_NUMBER))
            ->json('data.id');

        $response = $this->postJson("/api/v1/hr/identity-verifications/{$verificationId}/manual-review", [
            'reason' => 'Identity number typo suspected, resubmitted for human review.',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'pending');

        $verification = EmployeeIdentityVerification::query()->findOrFail($verificationId);
        $this->assertSame(IdentityVerificationStatus::RequiresReview, $verification->verification_status);
    }

    public function test_manual_review_approval(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $this->actingAsRole($tenantId, 'HR Officer');

        $verificationId = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload(self::NOT_FOUND_NUMBER))
            ->json('data.id');
        $reviewId = $this->postJson("/api/v1/hr/identity-verifications/{$verificationId}/manual-review", [
            'reason' => 'Manual passport check performed in person.',
        ])->json('data.id');

        $this->actingAsRole($tenantId, 'HR Manager');

        $response = $this->postJson("/api/v1/hr/identity-manual-reviews/{$reviewId}/approve", [
            'reviewer_notes' => 'Confirmed against physical passport.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_manual_review_rejection(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $this->actingAsRole($tenantId, 'HR Officer');

        $verificationId = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload(self::NOT_FOUND_NUMBER))
            ->json('data.id');
        $reviewId = $this->postJson("/api/v1/hr/identity-verifications/{$verificationId}/manual-review", [
            'reason' => 'Needs escalation.',
        ])->json('data.id');

        $this->actingAsRole($tenantId, 'HR Manager');

        $response = $this->postJson("/api/v1/hr/identity-manual-reviews/{$reviewId}/reject", [
            'reviewer_notes' => 'Documents did not match.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'rejected');
    }

    // --- Employee creation after confirmation ------------------------------------

    public function test_employee_creation_after_confirmation_links_and_stamps_identity(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $verificationId = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload())->json('data.id');
        $this->postJson("/api/v1/hr/identity-verifications/{$verificationId}/confirm")->assertOk();

        $response = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'John',
            'middle_name' => 'Peter',
            'last_name' => 'Mrema',
            'job_title' => 'Warehouse Clerk',
            'hire_date' => now()->subMonth()->toDateString(),
            'salary' => 500000,
            'identity_verification_id' => $verificationId,
            'identity_document_type' => 'national_id',
            'identity_number' => self::VERIFIED_NUMBER,
            'identity_country_code' => 'TZ',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.identity_verified', true)
            ->assertJsonPath('data.identity_verification_status', 'verified');

        $employeeId = $response->json('data.id');
        $verification = EmployeeIdentityVerification::query()->findOrFail($verificationId);
        $this->assertSame($employeeId, $verification->employee_id);

        // The raw identity number is never returned by the employee resource either.
        $this->assertStringNotContainsString(self::VERIFIED_NUMBER, $response->getContent());
    }

    // --- Payroll gating ------------------------------------------------------------

    public function test_payroll_activation_blocked_for_unverified_employee_when_required(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];

        Company::query()->where('tenant_id', $tenantId)->update(['require_identity_verification_before_payroll' => true]);

        $employeeId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', [
                'first_name' => 'Unverified',
                'last_name' => 'Employee',
                'job_title' => 'Clerk',
                'hire_date' => now()->subMonth()->toDateString(),
                'salary' => 400000,
                'preferred_payment_method' => 'cash',
            ])->json('data.id');

        $periodId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/payroll-periods', [
                'name' => 'July 2026',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'payment_date' => '2026-08-05',
            ])->json('data.id');

        $runId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-periods/{$periodId}/runs")
            ->json('data.id');

        $calculate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/payroll-runs/{$runId}/calculate");

        $calculate->assertOk();

        $employee = Employee::query()->findOrFail($employeeId);
        $this->assertFalse($employee->identity_verified);

        $runEmployee = DB::table('payroll_run_employees')
            ->where('payroll_run_id', $runId)
            ->where('employee_id', $employeeId)
            ->first();

        $this->assertSame('exception', $runEmployee->status);
        $this->assertStringContainsString('identity verification', strtolower($runEmployee->exception_notes));
    }

    public function test_payroll_activation_allowed_for_verified_employee(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $token = $registration['token'];

        Company::query()->where('tenant_id', $tenantId)->update(['require_identity_verification_before_payroll' => true]);

        // A single bearer-token identity throughout — Sanctum::actingAs() in
        // actingAsRole() would otherwise take over every subsequent request
        // and silently ignore this Authorization header (see feedback_testing_gotchas).
        $auth = fn () => $this->withHeader('Authorization', "Bearer {$token}");

        $verificationId = $auth()->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload())->json('data.id');
        $auth()->postJson("/api/v1/hr/identity-verifications/{$verificationId}/confirm")->assertOk();
        $employeeId = $auth()->postJson('/api/v1/hr/employees', [
            'first_name' => 'John',
            'last_name' => 'Mrema',
            'job_title' => 'Clerk',
            'hire_date' => now()->subMonth()->toDateString(),
            'salary' => 500000,
            'preferred_payment_method' => 'cash',
            'identity_verification_id' => $verificationId,
            'identity_document_type' => 'national_id',
            'identity_number' => self::VERIFIED_NUMBER,
            'identity_country_code' => 'TZ',
        ])->json('data.id');

        $periodId = $auth()->postJson('/api/v1/hr/payroll-periods', [
            'name' => 'July 2026',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'payment_date' => '2026-08-05',
        ])->json('data.id');

        $runId = $auth()->postJson("/api/v1/hr/payroll-periods/{$periodId}/runs")->json('data.id');

        $auth()->postJson("/api/v1/hr/payroll-runs/{$runId}/calculate")->assertOk();

        $runEmployee = DB::table('payroll_run_employees')
            ->where('payroll_run_id', $runId)
            ->where('employee_id', $employeeId)
            ->first();

        $this->assertSame('included', $runEmployee->status);
    }

    // --- Masking / encryption / hashing -----------------------------------------

    public function test_identity_number_is_masked_and_never_returned_raw(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $response = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload());

        $masked = $response->json('data.identity_number_masked') ?? EmployeeIdentityVerification::query()->latest()->first()->identity_number_masked;
        $this->assertMatchesRegularExpression('/^\d{4}\*+\d{2}$/', $masked);
        $this->assertStringNotContainsString(self::VERIFIED_NUMBER, $response->getContent());
    }

    public function test_identity_number_is_encrypted_at_rest_on_the_employee_record(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $verificationId = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload())->json('data.id');
        $this->postJson("/api/v1/hr/identity-verifications/{$verificationId}/confirm");

        $employeeId = $this->postJson('/api/v1/hr/employees', [
            'first_name' => 'John', 'last_name' => 'Mrema', 'job_title' => 'Clerk',
            'hire_date' => now()->subMonth()->toDateString(), 'salary' => 500000,
            'identity_verification_id' => $verificationId,
            'identity_document_type' => 'national_id',
            'identity_number' => self::VERIFIED_NUMBER,
            'identity_country_code' => 'TZ',
        ])->json('data.id');

        $rawColumnValue = DB::table('employees')->where('id', $employeeId)->value('identity_number');

        $this->assertNotNull($rawColumnValue);
        $this->assertStringNotContainsString(self::VERIFIED_NUMBER, $rawColumnValue);

        // But the Eloquent-cast accessor decrypts it back correctly.
        $employee = Employee::query()->findOrFail($employeeId);
        $this->assertSame(self::VERIFIED_NUMBER, $employee->identity_number);
    }

    public function test_identity_number_hash_is_deterministic_for_duplicate_detection(): void
    {
        $registration = $this->registerTenant();
        $this->actingAsRole($registration['user']['tenant_id'], 'HR Officer');

        $first = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload())->json('data.id');
        $second = $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload())->json('data.id');

        $hashes = EmployeeIdentityVerification::query()->whereIn('id', [$first, $second])->pluck('identity_number_hash');

        $this->assertCount(2, $hashes);
        $this->assertSame($hashes[0], $hashes[1]);
        $this->assertSame(64, strlen($hashes[0]));
    }

    // --- Audit trail ---------------------------------------------------------------

    public function test_audit_record_is_created_on_verification(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $this->actingAsRole($tenantId, 'HR Officer');

        $this->postJson('/api/v1/hr/identity-verifications', $this->verifyPayload());

        $this->assertTrue(
            AuditLog::query()->where('tenant_id', $tenantId)->where('action', 'identity_verification.succeeded')->exists()
        );
    }

    // --- Provider factory / configuration switching --------------------------------

    public function test_provider_factory_resolves_mock_by_default(): void
    {
        config(['identity.provider' => 'mock']);

        $provider = IdentityProviderFactory::make();

        $this->assertSame('mock', $provider->key());
        $this->assertFalse($provider->isLive());
    }

    public function test_provider_factory_resolves_nida_placeholder_which_is_unavailable(): void
    {
        config(['identity.provider' => 'nida']);

        $provider = IdentityProviderFactory::make();

        $this->assertSame('nida', $provider->key());
        $this->assertFalse($provider->isLive());
    }
}
