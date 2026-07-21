<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\DisciplinaryRecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ResolveDisciplinaryRecordRequest;
use App\Http\Requests\Hr\StoreDisciplinaryRecordRequest;
use App\Http\Resources\DisciplinaryRecordResource;
use App\Models\DisciplinaryRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DisciplinaryRecordController extends Controller
{
    private const WITH = ['employee', 'issuedBy'];

    public function index(Request $request)
    {
        return DisciplinaryRecordResource::collection(
            DisciplinaryRecord::query()
                ->with(self::WITH)
                ->when($request->query('employee_id'), fn ($query, $id) => $query->where('employee_id', $id))
                ->latest('incident_date')
                ->paginate(20)
        );
    }

    public function store(StoreDisciplinaryRecordRequest $request)
    {
        $record = DisciplinaryRecord::query()->create([
            ...$request->validated(),
            'status' => DisciplinaryRecordStatus::Issued,
            'issued_by' => Auth::id(),
            'created_by' => Auth::id(),
        ])->refresh();

        return new DisciplinaryRecordResource($record->load(self::WITH));
    }

    public function show(DisciplinaryRecord $disciplinaryRecord)
    {
        return new DisciplinaryRecordResource($disciplinaryRecord->load(self::WITH));
    }

    public function acknowledge(ResolveDisciplinaryRecordRequest $request, DisciplinaryRecord $disciplinaryRecord)
    {
        abort_if($disciplinaryRecord->status !== DisciplinaryRecordStatus::Issued, 409, 'Only issued records can be acknowledged.');

        $disciplinaryRecord->update([
            'status' => DisciplinaryRecordStatus::Acknowledged,
            'employee_response' => $request->validated('employee_response'),
        ]);

        return new DisciplinaryRecordResource($disciplinaryRecord->fresh()->load(self::WITH));
    }

    public function resolve(DisciplinaryRecord $disciplinaryRecord)
    {
        abort_if(
            ! in_array($disciplinaryRecord->status, [DisciplinaryRecordStatus::Acknowledged, DisciplinaryRecordStatus::Appealed], true),
            409,
            'Only acknowledged or appealed records can be resolved.',
        );

        $disciplinaryRecord->update(['status' => DisciplinaryRecordStatus::Resolved, 'resolved_at' => now()]);

        return new DisciplinaryRecordResource($disciplinaryRecord->fresh()->load(self::WITH));
    }

    public function destroy(DisciplinaryRecord $disciplinaryRecord)
    {
        $disciplinaryRecord->delete();

        return response()->json(status: 204);
    }
}
