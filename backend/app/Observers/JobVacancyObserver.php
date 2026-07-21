<?php

namespace App\Observers;

use App\Models\JobVacancy;
use App\Services\Audit\AuditLogger;

class JobVacancyObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(JobVacancy $vacancy): void
    {
        $this->auditLogger->log(
            action: 'job_vacancy.created',
            auditable: $vacancy,
            newValues: $vacancy->only(['title', 'status', 'number_of_openings']),
            tenantId: $vacancy->tenant_id,
        );
    }

    public function updated(JobVacancy $vacancy): void
    {
        if (! $vacancy->wasChanged('status')) {
            return;
        }

        $this->auditLogger->log(
            action: 'job_vacancy.status_changed',
            auditable: $vacancy,
            oldValues: ['status' => $vacancy->getOriginal('status')],
            newValues: ['status' => $vacancy->status->value],
            tenantId: $vacancy->tenant_id,
        );
    }
}
