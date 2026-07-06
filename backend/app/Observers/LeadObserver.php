<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class LeadObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(Lead $lead): void
    {
        $this->auditLogger->log(
            action: 'lead.created',
            auditable: $lead,
            newValues: $lead->only(['company_name', 'contact_name', 'status', 'source']),
            tenantId: $lead->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'crm.leads.view', 'lead.created', 'New lead',
            "New lead: {$lead->company_name}.",
            $lead, Auth::id(),
        );
    }

    public function updated(Lead $lead): void
    {
        $this->auditLogger->log(
            action: 'lead.updated',
            auditable: $lead,
            oldValues: $lead->getOriginal(),
            newValues: $lead->getChanges(),
            tenantId: $lead->tenant_id,
        );
    }

    public function deleted(Lead $lead): void
    {
        $this->auditLogger->log(
            action: 'lead.deleted',
            auditable: $lead,
            oldValues: $lead->only(['company_name', 'contact_name']),
            tenantId: $lead->tenant_id,
        );
    }
}
