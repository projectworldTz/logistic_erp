<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreScheduledReportRequest;
use App\Http\Requests\Reports\UpdateScheduledReportRequest;
use App\Http\Resources\ScheduledReportResource;
use App\Models\ScheduledReport;
use Illuminate\Http\Request;

class ScheduledReportController extends Controller
{
    public function index()
    {
        return ScheduledReportResource::collection(
            ScheduledReport::query()->latest()->get()
        );
    }

    public function store(StoreScheduledReportRequest $request)
    {
        $report = ScheduledReport::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return new ScheduledReportResource($report);
    }

    public function update(UpdateScheduledReportRequest $request, ScheduledReport $scheduledReport)
    {
        $scheduledReport->update($request->validated());

        return new ScheduledReportResource($scheduledReport);
    }

    public function destroy(ScheduledReport $scheduledReport)
    {
        $scheduledReport->delete();

        return response()->json(status: 204);
    }
}
