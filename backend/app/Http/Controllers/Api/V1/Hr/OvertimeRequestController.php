<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RejectOvertimeRequestRequest;
use App\Http\Requests\Hr\StoreOvertimeRequestRequest;
use App\Http\Resources\OvertimeRequestResource;
use App\Models\OvertimeRequest;
use App\Services\Payroll\OvertimeRequestService;
use Illuminate\Http\Request;

class OvertimeRequestController extends Controller
{
    private const WITH = ['employee'];

    public function index(Request $request)
    {
        return OvertimeRequestResource::collection(
            OvertimeRequest::query()
                ->with(self::WITH)
                ->when($request->query('employee_id'), fn ($query, $id) => $query->where('employee_id', $id))
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest('date')
                ->paginate(20)
        );
    }

    public function store(StoreOvertimeRequestRequest $request)
    {
        $overtimeRequest = OvertimeRequest::query()->create($request->validated())->refresh();

        return new OvertimeRequestResource($overtimeRequest->load(self::WITH));
    }

    public function destroy(OvertimeRequest $overtimeRequest)
    {
        abort_if($overtimeRequest->status !== \App\Enums\OvertimeRequestStatus::Pending, 409, 'Only pending overtime requests can be deleted.');

        $overtimeRequest->delete();

        return response()->json(status: 204);
    }

    public function approve(OvertimeRequest $overtimeRequest, OvertimeRequestService $service)
    {
        $overtimeRequest = $service->approve($overtimeRequest);

        return new OvertimeRequestResource($overtimeRequest->load(self::WITH));
    }

    public function reject(RejectOvertimeRequestRequest $request, OvertimeRequest $overtimeRequest, OvertimeRequestService $service)
    {
        $overtimeRequest = $service->reject($overtimeRequest, $request->validated('reason'));

        return new OvertimeRequestResource($overtimeRequest->load(self::WITH));
    }
}
