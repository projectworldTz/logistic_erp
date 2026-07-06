<?php

namespace App\Observers;

use App\Models\JournalEntry;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class JournalEntryObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
    ) {}

    public function created(JournalEntry $journalEntry): void
    {
        $journalEntry->entry_number = 'JE-'.now()->format('Y').'-'.str_pad((string) $journalEntry->id, 5, '0', STR_PAD_LEFT);
        $journalEntry->saveQuietly();

        $this->auditLogger->log(
            action: 'journal_entry.created',
            auditable: $journalEntry,
            newValues: $journalEntry->only(['entry_number', 'entry_date', 'status']),
            tenantId: $journalEntry->tenant_id,
        );

        $this->notifications->notifyModuleUsers(
            'accounting.journal.view', 'journal_entry.created', 'New journal entry',
            "Journal entry {$journalEntry->entry_number} was created.",
            $journalEntry, Auth::id(),
        );
    }

    public function updated(JournalEntry $journalEntry): void
    {
        $this->auditLogger->log(
            action: 'journal_entry.updated',
            auditable: $journalEntry,
            oldValues: $journalEntry->getOriginal(),
            newValues: $journalEntry->getChanges(),
            tenantId: $journalEntry->tenant_id,
        );
    }

    public function deleted(JournalEntry $journalEntry): void
    {
        $this->auditLogger->log(
            action: 'journal_entry.deleted',
            auditable: $journalEntry,
            oldValues: $journalEntry->only(['entry_number']),
            tenantId: $journalEntry->tenant_id,
        );
    }
}
