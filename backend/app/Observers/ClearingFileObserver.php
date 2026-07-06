<?php

namespace App\Observers;

use App\Models\ClearingFile;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class ClearingFileObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(ClearingFile $clearingFile): void
    {
        $clearingFile->reference_no = 'CLR-'.now()->format('Y').'-'.str_pad((string) $clearingFile->id, 5, '0', STR_PAD_LEFT);
        $clearingFile->saveQuietly();

        $this->auditLogger->log(
            action: 'clearing_file.created',
            auditable: $clearingFile,
            newValues: $clearingFile->only(['reference_no', 'customer_id', 'direction', 'status']),
            tenantId: $clearingFile->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'clearing.files.view', 'clearing_file.created', 'New clearing file',
            "Clearing file {$clearingFile->reference_no} was created.",
            $clearingFile, Auth::id(),
        );
    }

    public function updated(ClearingFile $clearingFile): void
    {
        $this->auditLogger->log(
            action: 'clearing_file.updated',
            auditable: $clearingFile,
            oldValues: $clearingFile->getOriginal(),
            newValues: $clearingFile->getChanges(),
            tenantId: $clearingFile->tenant_id,
        );
    }

    public function deleted(ClearingFile $clearingFile): void
    {
        $this->auditLogger->log(
            action: 'clearing_file.deleted',
            auditable: $clearingFile,
            oldValues: $clearingFile->only(['reference_no', 'customer_id']),
            tenantId: $clearingFile->tenant_id,
        );
    }
}
