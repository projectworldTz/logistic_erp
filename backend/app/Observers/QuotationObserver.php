<?php

namespace App\Observers;

use App\Models\Quotation;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class QuotationObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(Quotation $quotation): void
    {
        $quotation->quotation_number = 'QT-'.now()->format('Y').'-'.str_pad((string) $quotation->id, 5, '0', STR_PAD_LEFT);
        $quotation->saveQuietly();

        $this->auditLogger->log(
            action: 'quotation.created',
            auditable: $quotation,
            newValues: $quotation->only(['quotation_number', 'customer_id', 'total_amount', 'status']),
            tenantId: $quotation->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'quotations.items.view', 'quotation.created', 'New quotation',
            "Quotation {$quotation->quotation_number} was created.",
            $quotation, Auth::id(),
        );
    }

    public function updated(Quotation $quotation): void
    {
        $this->auditLogger->log(
            action: 'quotation.updated',
            auditable: $quotation,
            oldValues: $quotation->getOriginal(),
            newValues: $quotation->getChanges(),
            tenantId: $quotation->tenant_id,
        );
    }

    public function deleted(Quotation $quotation): void
    {
        $this->auditLogger->log(
            action: 'quotation.deleted',
            auditable: $quotation,
            oldValues: $quotation->only(['quotation_number', 'customer_id']),
            tenantId: $quotation->tenant_id,
        );
    }
}
