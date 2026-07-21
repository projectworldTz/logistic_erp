<?php

namespace Tests\Feature\Hr;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function registerTenant(): array
    {
        $response = $this->postJson('/api/v1/tenants/register', [
            'plan_code' => 'starter',
            'owner' => ['name' => 'Jane Doe', 'email' => 'jane@acme.test', 'password' => 'SecurePass123'],
            'company' => [
                'name' => 'Acme Logistics', 'country' => 'Kenya', 'city' => 'Nairobi', 'address' => '123 Port Rd',
                'currency' => 'USD', 'timezone' => 'Africa/Nairobi', 'industry' => 'Freight Forwarding',
            ],
        ]);

        return $response->json();
    }

    public function test_hr_dashboard_returns_a_full_summary_with_no_data(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/hr/dashboard');

        $response->assertOk();
        $response->assertJsonStructure([
            'headcount' => ['total', 'by_department', 'by_status'],
            'attendance' => ['today'],
            'leave' => ['pending_requests'],
            'expiring' => ['contracts', 'documents'],
            'payroll' => ['last_run', 'pending_approval_runs', 'trend'],
            'loans' => ['pending_loans', 'pending_advances', 'outstanding_loan_balance'],
            'recruitment' => ['open_vacancies', 'candidates_in_pipeline', 'total_candidates'],
            'exits' => ['in_progress', 'open_disciplinary'],
        ]);
        $response->assertJsonPath('headcount.total', 0);
        $response->assertJsonPath('payroll.last_run', null);
    }

    public function test_hr_dashboard_reflects_real_data(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', [
                'first_name' => 'John', 'last_name' => 'Kamau', 'hire_date' => now()->subYear()->toDateString(),
            ])->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/job-vacancies', ['title' => 'Driver'])->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/hr/dashboard');
        $response->assertOk();
        $response->assertJsonPath('headcount.total', 1);
        $response->assertJsonPath('recruitment.open_vacancies', 1);
    }
}
