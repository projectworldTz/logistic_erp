<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecruitmentOnboardingTest extends TestCase
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

    public function test_full_recruitment_pipeline_from_vacancy_to_hired_employee_with_onboarding(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $vacancy = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/job-vacancies', [
                'title' => 'Customs Clearing Officer',
                'employment_type' => 'full_time',
                'number_of_openings' => 1,
            ]);
        $vacancy->assertCreated();
        $vacancyId = $vacancy->json('data.id');

        $candidate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/candidates', [
                'first_name' => 'Grace', 'last_name' => 'Mwangi', 'email' => 'grace@example.com', 'source' => 'referral',
            ]);
        $candidate->assertCreated();
        $candidate->assertJsonPath('data.name', 'Grace Mwangi');
        $candidateId = $candidate->json('data.id');

        $application = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/job-applications', [
                'job_vacancy_id' => $vacancyId, 'candidate_id' => $candidateId, 'applied_date' => now()->toDateString(),
            ]);
        $application->assertCreated();
        $application->assertJsonPath('data.status', 'applied');
        $applicationId = $application->json('data.id');

        $interview = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/interviews', [
                'job_application_id' => $applicationId, 'scheduled_at' => now()->addDays(2)->toDateTimeString(), 'mode' => 'video',
            ]);
        $interview->assertCreated();
        $interviewId = $interview->json('data.id');

        $completeInterview = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/interviews/{$interviewId}/complete", ['status' => 'completed', 'feedback' => 'Strong candidate.', 'rating' => 4.5]);
        $completeInterview->assertOk();

        $moveToOffer = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/hr/job-applications/{$applicationId}/status", ['status' => 'offer']);
        $moveToOffer->assertOk();
        $moveToOffer->assertJsonPath('data.status', 'offer');

        $hire = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/job-applications/{$applicationId}/hire", ['employment_type' => 'full_time']);
        $hire->assertCreated();
        $hire->assertJsonPath('data.application.status', 'hired');
        $employeeId = $hire->json('data.employee_id');
        $this->assertNotNull($employeeId);

        // Employee record was actually created with the candidate's details.
        $employee = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/hr/employees/{$employeeId}");
        $employee->assertOk();
        $employee->assertJsonPath('data.name', 'Grace Mwangi');
        $employee->assertJsonPath('data.email', 'grace@example.com');

        // Onboarding checklist was auto-created with the default task set.
        $checklists = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/hr/onboarding-checklists');
        $checklists->assertOk();
        $checklists->assertJsonCount(1, 'data');
        $checklists->assertJsonPath('data.0.status', 'in_progress');
        $checklistId = $checklists->json('data.0.id');

        $checklistDetail = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/hr/onboarding-checklists/{$checklistId}");
        $checklistDetail->assertOk();
        $taskCount = count($checklistDetail->json('data.tasks'));
        $this->assertGreaterThan(0, $taskCount);

        // Completing every task auto-completes the checklist.
        foreach ($checklistDetail->json('data.tasks') as $task) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->postJson("/api/v1/hr/onboarding-tasks/{$task['id']}/toggle")->assertOk();
        }

        $finalChecklist = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/hr/onboarding-checklists/{$checklistId}");
        $finalChecklist->assertJsonPath('data.status', 'completed');
        $finalChecklist->assertJsonPath('data.progress', 100);

        // A hired application can't be re-hired or deleted.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/job-applications/{$applicationId}/hire")->assertStatus(409);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/hr/job-applications/{$applicationId}")->assertStatus(409);
    }

    public function test_duplicate_application_for_the_same_vacancy_and_candidate_is_rejected(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $vacancyId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/job-vacancies', ['title' => 'Driver'])->json('data.id');
        $candidateId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/candidates', ['first_name' => 'Peter', 'last_name' => 'Otieno'])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/job-applications', [
                'job_vacancy_id' => $vacancyId, 'candidate_id' => $candidateId, 'applied_date' => now()->toDateString(),
            ])->assertCreated();

        $duplicate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/job-applications', [
                'job_vacancy_id' => $vacancyId, 'candidate_id' => $candidateId, 'applied_date' => now()->toDateString(),
            ]);
        $duplicate->assertUnprocessable();
    }

    public function test_recruitment_records_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/hr/job-vacancies', ['title' => 'Warehouse Supervisor'])->assertCreated();
        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/hr/candidates', ['first_name' => 'Ann', 'last_name' => 'Kioko'])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/hr/job-vacancies')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/candidates')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_user_without_recruitment_manage_permission_cannot_create_a_vacancy(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $driver = User::factory()->create(['tenant_id' => $tenantId]);
        $driver->assignRole('Driver');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($driver, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->postJson('/api/v1/hr/job-vacancies', ['title' => 'Accountant'])->assertForbidden();
    }
}
