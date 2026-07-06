<?php

namespace App\Services\Accounting;

use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Create a journal entry with its lines in one atomic transaction.
     * Line balance (sum debit == sum credit) is validated upstream by the
     * FormRequest — this only persists what's already been verified.
     */
    public function create(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            $entry = JournalEntry::query()->create([
                'entry_date' => $data['entry_date'],
                'description' => $data['description'] ?? null,
                'reference' => $data['reference'] ?? null,
                'created_by' => Auth::id(),
            ])->refresh();

            foreach ($data['lines'] as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $entry->load('lines.account');
        });
    }

    /**
     * Replace a draft entry's header fields and, if provided, its lines.
     * Only draft entries may be edited — posted entries are immutable.
     */
    public function update(JournalEntry $entry, array $data): JournalEntry
    {
        abort_if($entry->status !== JournalEntryStatus::Draft, 409, 'Only draft journal entries can be edited.');

        return DB::transaction(function () use ($entry, $data) {
            $entry->update(array_intersect_key($data, array_flip(['entry_date', 'description', 'reference'])));

            if (isset($data['lines'])) {
                $entry->lines()->delete();

                foreach ($data['lines'] as $line) {
                    $entry->lines()->create([
                        'account_id' => $line['account_id'],
                        'debit' => $line['debit'] ?? 0,
                        'credit' => $line['credit'] ?? 0,
                        'description' => $line['description'] ?? null,
                    ]);
                }
            }

            return $entry->load('lines.account');
        });
    }

    public function post(JournalEntry $entry): JournalEntry
    {
        abort_if($entry->status !== JournalEntryStatus::Draft, 409, 'Only draft journal entries can be posted.');

        $entry->update([
            'status' => JournalEntryStatus::Posted,
            'posted_at' => now(),
        ]);

        $this->auditLogger->log(
            action: 'journal_entry.posted',
            auditable: $entry,
            newValues: ['entry_number' => $entry->entry_number],
            tenantId: $entry->tenant_id,
        );

        return $entry;
    }

    public function void(JournalEntry $entry): JournalEntry
    {
        abort_if($entry->status === JournalEntryStatus::Voided, 409, 'Journal entry is already voided.');

        $entry->update(['status' => JournalEntryStatus::Voided]);

        $this->auditLogger->log(
            action: 'journal_entry.voided',
            auditable: $entry,
            newValues: ['entry_number' => $entry->entry_number],
            tenantId: $entry->tenant_id,
        );

        return $entry;
    }
}
