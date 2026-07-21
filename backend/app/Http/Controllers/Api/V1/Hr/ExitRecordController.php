<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\ExitRecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreExitRecordRequest;
use App\Http\Requests\Hr\UpdateExitRecordRequest;
use App\Http\Resources\ExitRecordResource;
use App\Models\ExitRecord;
use App\Services\Hr\ExitSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExitRecordController extends Controller
{
    private const WITH = ['employee'];

    public function index(Request $request)
    {
        return ExitRecordResource::collection(
            ExitRecord::query()
                ->with(self::WITH)
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest('notice_date')
                ->paginate(20)
        );
    }

    public function store(StoreExitRecordRequest $request, ExitSettlementService $settlementService)
    {
        $record = ExitRecord::query()->create([
            ...$request->validated(),
            'status' => ExitRecordStatus::Initiated,
            'initiated_by' => Auth::id(),
            'created_by' => Auth::id(),
        ])->refresh();

        $record = $settlementService->computeAndStore($record);

        return new ExitRecordResource($record->load(self::WITH));
    }

    public function show(ExitRecord $exitRecord)
    {
        return new ExitRecordResource($exitRecord->load(self::WITH));
    }

    public function update(UpdateExitRecordRequest $request, ExitRecord $exitRecord, ExitSettlementService $settlementService)
    {
        abort_if($exitRecord->status === ExitRecordStatus::Completed, 409, 'This exit record is already completed.');

        $exitRecord->update($request->validated());

        if ($exitRecord->status === ExitRecordStatus::Initiated && ($exitRecord->assets_cleared || $exitRecord->handover_completed)) {
            $exitRecord->update(['status' => ExitRecordStatus::InProgress]);
        }
        if ($exitRecord->assets_cleared && $exitRecord->handover_completed) {
            $exitRecord->update(['status' => ExitRecordStatus::Cleared]);
        }

        $exitRecord = $settlementService->computeAndStore($exitRecord->fresh());

        return new ExitRecordResource($exitRecord->load(self::WITH));
    }

    public function complete(ExitRecord $exitRecord, ExitSettlementService $settlementService)
    {
        $record = $settlementService->completeExit($exitRecord);

        return new ExitRecordResource($record->load(self::WITH));
    }
}
