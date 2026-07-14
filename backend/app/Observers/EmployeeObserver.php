<?php

namespace App\Observers;

use App\Models\Employee;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class EmployeeObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(Employee $employee): void
    {
        $employee->employee_number = 'EMP-'.now()->format('Y').'-'.str_pad((string) $employee->id, 5, '0', STR_PAD_LEFT);
        $employee->saveQuietly();

        $this->auditLogger->log(
            action: 'employee.created',
            auditable: $employee,
            newValues: $employee->only(['employee_number', 'name', 'job_title', 'status']),
            tenantId: $employee->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'hr.employees.view', 'employee.created', 'New employee',
            "{$employee->name} was added as an employee.",
            $employee, Auth::id(),
        );
    }

    public function updated(Employee $employee): void
    {
        $this->auditLogger->log(
            action: 'employee.updated',
            auditable: $employee,
            oldValues: $employee->getOriginal(),
            newValues: $employee->getChanges(),
            tenantId: $employee->tenant_id,
        );
    }

    public function deleted(Employee $employee): void
    {
        $this->auditLogger->log(
            action: 'employee.deleted',
            auditable: $employee,
            oldValues: $employee->only(['employee_number', 'name']),
            tenantId: $employee->tenant_id,
        );
    }
}
