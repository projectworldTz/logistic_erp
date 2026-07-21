<?php

namespace App\Observers;

use App\Models\ExitRecord;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class ExitRecordObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(ExitRecord $record): void
    {
        $this->auditLogger->log(
            action: 'exit_record.initiated',
            auditable: $record,
            newValues: $record->only(['employee_id', 'exit_type', 'last_working_date']),
            tenantId: $record->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'hr.exits.manage', 'exit_record.initiated', 'Employee exit initiated',
            'An employee exit process has been initiated and needs clearance.',
            $record, Auth::id(),
        );
    }

    public function updated(ExitRecord $record): void
    {
        if (! $record->wasChanged('status')) {
            return;
        }

        $this->auditLogger->log(
            action: 'exit_record.status_changed',
            auditable: $record,
            oldValues: ['status' => $record->getOriginal('status')],
            newValues: ['status' => $record->status->value, 'final_settlement_amount' => $record->final_settlement_amount],
            tenantId: $record->tenant_id,
        );
    }
}
