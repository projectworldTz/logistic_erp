<?php

namespace App\Services\Hr;

use App\Enums\OnboardingChecklistStatus;
use App\Models\Employee;
use App\Models\OnboardingChecklist;
use Illuminate\Support\Facades\Auth;

class OnboardingService
{
    /**
     * The default task set every new hire gets — editable per-employee
     * afterward (add/remove/reorder), this is just the starting template
     * rather than a rigid fixed process.
     */
    private const DEFAULT_TASKS = [
        'Collect signed employment contract',
        'Collect ID / passport and statutory registration documents',
        'Set up company email and system access',
        'Assign designation and reporting manager',
        'Issue company assets (laptop, phone, uniform, etc.)',
        'Complete orientation / company policy briefing',
        'Submit bank details for payroll',
        'Enroll in statutory contribution schemes',
    ];

    public function startForEmployee(Employee $employee): OnboardingChecklist
    {
        $checklist = OnboardingChecklist::query()->firstOrCreate(
            ['tenant_id' => $employee->tenant_id, 'employee_id' => $employee->id],
            ['status' => OnboardingChecklistStatus::InProgress, 'started_at' => now(), 'created_by' => Auth::id()],
        );

        if ($checklist->wasRecentlyCreated) {
            foreach (self::DEFAULT_TASKS as $index => $title) {
                $checklist->tasks()->create([
                    'tenant_id' => $employee->tenant_id,
                    'title' => $title,
                    'sort_order' => $index,
                ]);
            }
        }

        return $checklist->refresh();
    }
}
