<?php

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Enums\JournalEntryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreJournalEntryRequest;
use App\Http\Requests\Accounting\UpdateJournalEntryRequest;
use App\Http\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        return JournalEntryResource::collection(
            JournalEntry::query()
                ->with(['lines.account', 'createdBy'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest('entry_date')
                ->paginate(20)
        );
    }

    public function store(StoreJournalEntryRequest $request, JournalEntryService $service)
    {
        $entry = $service->create($request->validated());

        return new JournalEntryResource($entry);
    }

    public function show(JournalEntry $journalEntry)
    {
        return new JournalEntryResource($journalEntry->load(['lines.account', 'createdBy']));
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry, JournalEntryService $service)
    {
        $entry = $service->update($journalEntry, $request->validated());

        return new JournalEntryResource($entry);
    }

    public function destroy(JournalEntry $journalEntry)
    {
        abort_if($journalEntry->status !== JournalEntryStatus::Draft, 409, 'Only draft journal entries can be deleted.');

        $journalEntry->delete();

        return response()->json(status: 204);
    }

    public function post(JournalEntry $journalEntry, JournalEntryService $service)
    {
        $entry = $service->post($journalEntry);

        return new JournalEntryResource($entry->load(['lines.account', 'createdBy']));
    }

    public function void(JournalEntry $journalEntry, JournalEntryService $service)
    {
        $entry = $service->void($journalEntry);

        return new JournalEntryResource($entry->load(['lines.account', 'createdBy']));
    }
}
