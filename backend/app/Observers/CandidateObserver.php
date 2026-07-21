<?php

namespace App\Observers;

use App\Models\Candidate;
use App\Services\Audit\AuditLogger;

class CandidateObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(Candidate $candidate): void
    {
        $this->auditLogger->log(
            action: 'candidate.created',
            auditable: $candidate,
            newValues: $candidate->only(['first_name', 'last_name', 'email']),
            tenantId: $candidate->tenant_id,
        );
    }
}
