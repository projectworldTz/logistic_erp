<?php

namespace App\Observers;

use App\Models\EmployeeDocument;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class EmployeeDocumentObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(EmployeeDocument $document): void
    {
        if ($document->root_document_id === null) {
            $document->root_document_id = $document->id;
            $document->saveQuietly();
        }

        $this->auditLogger->log(
            action: 'employee_document.uploaded',
            auditable: $document,
            newValues: $document->only(['employee_id', 'document_type', 'file_name', 'version']),
            tenantId: $document->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'hr.employees.documents.manage', 'employee_document.uploaded', 'Employee document uploaded',
            "A new {$document->document_type->value} document was uploaded and needs verification.",
            $document, Auth::id(),
        );
    }

    public function updated(EmployeeDocument $document): void
    {
        $this->auditLogger->log(
            action: 'employee_document.updated',
            auditable: $document,
            oldValues: $document->getOriginal(),
            newValues: $document->getChanges(),
            tenantId: $document->tenant_id,
        );
    }

    public function deleted(EmployeeDocument $document): void
    {
        $this->auditLogger->log(
            action: 'employee_document.deleted',
            auditable: $document,
            oldValues: $document->only(['employee_id', 'document_type', 'file_name']),
            tenantId: $document->tenant_id,
        );
    }
}
