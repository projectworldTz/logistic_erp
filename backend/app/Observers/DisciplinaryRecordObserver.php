<?php

namespace App\Observers;

use App\Models\DisciplinaryRecord;
use App\Services\Audit\AuditLogger;

class DisciplinaryRecordObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(DisciplinaryRecord $record): void
    {
        $this->auditLogger->log(
            action: 'disciplinary_record.created',
            auditable: $record,
            newValues: $record->only(['employee_id', 'category', 'severity', 'status']),
            tenantId: $record->tenant_id,
        );
    }

    public function updated(DisciplinaryRecord $record): void
    {
        if (! $record->wasChanged('status')) {
            return;
        }

        $this->auditLogger->log(
            action: 'disciplinary_record.status_changed',
            auditable: $record,
            oldValues: ['status' => $record->getOriginal('status')],
            newValues: ['status' => $record->status->value],
            tenantId: $record->tenant_id,
        );
    }
}
