<?php

namespace App\Observers;

use App\Models\JobApplication;
use App\Services\Audit\AuditLogger;

class JobApplicationObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(JobApplication $application): void
    {
        $this->auditLogger->log(
            action: 'job_application.created',
            auditable: $application,
            newValues: $application->only(['job_vacancy_id', 'candidate_id', 'status']),
            tenantId: $application->tenant_id,
        );
    }

    public function updated(JobApplication $application): void
    {
        if (! $application->wasChanged('status')) {
            return;
        }

        $this->auditLogger->log(
            action: 'job_application.status_changed',
            auditable: $application,
            oldValues: ['status' => $application->getOriginal('status')],
            newValues: ['status' => $application->status->value, 'converted_employee_id' => $application->converted_employee_id],
            tenantId: $application->tenant_id,
        );
    }
}
