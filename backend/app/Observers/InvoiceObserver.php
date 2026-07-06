<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class InvoiceObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(Invoice $invoice): void
    {
        $invoice->invoice_number = 'INV-'.now()->format('Y').'-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT);
        $invoice->saveQuietly();

        $this->auditLogger->log(
            action: 'invoice.created',
            auditable: $invoice,
            newValues: $invoice->only(['invoice_number', 'customer_id', 'total_amount', 'status']),
            tenantId: $invoice->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'finance.invoices.view', 'invoice.created', 'New invoice',
            "Invoice {$invoice->invoice_number} was created.",
            $invoice, Auth::id(),
        );
    }

    public function updated(Invoice $invoice): void
    {
        $this->auditLogger->log(
            action: 'invoice.updated',
            auditable: $invoice,
            oldValues: $invoice->getOriginal(),
            newValues: $invoice->getChanges(),
            tenantId: $invoice->tenant_id,
        );
    }

    public function deleted(Invoice $invoice): void
    {
        $this->auditLogger->log(
            action: 'invoice.deleted',
            auditable: $invoice,
            oldValues: $invoice->only(['invoice_number', 'customer_id']),
            tenantId: $invoice->tenant_id,
        );
    }
}
