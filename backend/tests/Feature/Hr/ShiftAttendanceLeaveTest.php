<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShiftAttendanceLeaveTest extends TestCase
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

    private function createEmployee(string $token, array $overrides = []): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employees', array_merge([
                'first_name' => 'John',
                'last_name' => 'Kamau',
                'hire_date' => now()->subYear()->toDateString(),
            ], $overrides))
            ->json('data.id');
    }

    private function createShift(string $token, array $overrides = []): int
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/shifts', array_merge([
                'name' => 'Day Shift',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'grace_minutes' => 10,
            ], $overrides))
            ->json('data.id');
    }

    public function test_shift_can_be_created_and_assigned_to_an_employee(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);
        $shiftId = $this->createShift($token);

        $assign = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/employee-shifts', [
                'employee_id' => $employeeId,
                'shift_id' => $shiftId,
                'effective_date' => now()->toDateString(),
            ]);

        $assign->assertCreated();
        $assign->assertJsonPath('data.shift.name', 'Day Shift');
    }

    public function test_attendance_late_minutes_are_computed_from_the_assigned_shift(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);
        $shiftId = $this->createShift($token);
        $today = now()->startOfDay();

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/attendance', [
                'employee_id' => $employeeId,
                'shift_id' => $shiftId,
                'date' => $today->toDateString(),
                'status' => 'late',
                // Shift starts 08:00 + 10 min grace = 08:10. Checked in 08:25 -> 15 min late.
                'check_in' => $today->copy()->setTime(8, 25)->toDateTimeString(),
            ]);

        $create->assertCreated();
        $create->assertJsonPath('data.late_minutes', 15);
    }

    public function test_attendance_csv_import_creates_records_and_skips_duplicates(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);
        $employee = \App\Models\Employee::findOrFail($employeeId);

        $csv = "employee_number,date,status\n{$employee->employee_number},".now()->toDateString().",present\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('attendance.csv', $csv);

        $import = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/reports/import/attendance', ['file' => $file]);

        $import->assertOk();
        $this->assertSame(1, $import->json('created'));
        $this->assertDatabaseHas('attendance_records', ['employee_id' => $employeeId, 'source' => 'import']);

        // Re-importing the same row should be skipped as a duplicate, not crash.
        $file2 = \Illuminate\Http\UploadedFile::fake()->createWithContent('attendance.csv', $csv);
        $reimport = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/reports/import/attendance', ['file' => $file2]);

        $reimport->assertOk();
        $this->assertSame(0, $reimport->json('created'));
        $this->assertCount(1, $reimport->json('errors'));
    }

    public function test_leave_request_lifecycle_submit_approve_deducts_balance(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);

        $leaveTypeId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/leave-types', ['name' => 'Annual Leave', 'default_annual_days' => 21])
            ->json('data.id');

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/leave-requests', [
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'start_date' => now()->addDays(5)->toDateString(),
                'end_date' => now()->addDays(9)->toDateString(),
                'reason' => 'Family trip',
            ]);

        $create->assertCreated();
        $leaveRequestId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'pending');
        $create->assertJsonPath('data.days', '5.0');

        $approve = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/leave-requests/{$leaveRequestId}/approve");
        $approve->assertOk();
        $approve->assertJsonPath('data.status', 'approved');

        $balance = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/hr/leave-balances?employee_id={$employeeId}&year=".now()->addDays(5)->year);
        $balance->assertOk();
        $balance->assertJsonPath('data.0.used_days', '5.0');
    }

    public function test_leave_request_rejection_requires_a_reason_and_does_not_touch_balance(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);
        $leaveTypeId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/leave-types', ['name' => 'Sick Leave'])
            ->json('data.id');

        $leaveRequestId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/leave-requests', [
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'start_date' => now()->addDay()->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
            ])->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/leave-requests/{$leaveRequestId}/reject", [])
            ->assertUnprocessable();

        $reject = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/leave-requests/{$leaveRequestId}/reject", ['reason' => 'Insufficient notice']);

        $reject->assertOk();
        $reject->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseMissing('leave_balances', ['employee_id' => $employeeId]);
    }

    public function test_timesheet_can_be_created_and_approved(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $employeeId = $this->createEmployee($token);

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/timesheets', [
                'employee_id' => $employeeId,
                'date' => now()->toDateString(),
                'total_hours' => 8,
                'activity' => 'Customs clearance for job CF-001',
            ]);

        $create->assertCreated();
        $timesheetId = $create->json('data.id');
        $create->assertJsonPath('data.status', 'pending');

        $approve = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/hr/timesheets/{$timesheetId}/approve");
        $approve->assertOk();
        $approve->assertJsonPath('data.status', 'approved');
    }

    public function test_hr_leave_and_shift_records_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $this->createShift($registrationA['token']);
        $employeeIdA = $this->createEmployee($registrationA['token']);
        $leaveTypeIdA = $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/hr/leave-types', ['name' => 'Annual Leave'])
            ->json('data.id');
        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/hr/leave-requests', [
                'employee_id' => $employeeIdA,
                'leave_type_id' => $leaveTypeIdA,
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/hr/shifts')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/leave-types')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/hr/leave-requests')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_user_without_leave_permission_cannot_approve(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];
        $employeeId = $this->createEmployee($token);
        $leaveTypeId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/leave-types', ['name' => 'Annual Leave'])
            ->json('data.id');
        $leaveRequestId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/hr/leave-requests', [
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ])->json('data.id');

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $driver = User::factory()->create(['tenant_id' => $tenantId]);
        $driver->assignRole('Driver');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($driver, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->postJson("/api/v1/hr/leave-requests/{$leaveRequestId}/approve")->assertForbidden();
    }
}
