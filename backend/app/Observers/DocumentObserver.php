<?php

namespace App\Observers;

use App\Models\Document;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class DocumentObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(Document $document): void
    {
        $this->auditLogger->log(
            action: 'document.created',
            auditable: $document,
            newValues: $document->only(['file_name', 'category', 'customer_id']),
            tenantId: $document->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'documents.files.view', 'document.created', 'New document',
            "A new document was uploaded: {$document->file_name}.",
            $document, Auth::id(),
        );
    }

    public function deleted(Document $document): void
    {
        $this->auditLogger->log(
            action: 'document.deleted',
            auditable: $document,
            oldValues: $document->only(['file_name', 'category']),
            tenantId: $document->tenant_id,
        );
    }
}
