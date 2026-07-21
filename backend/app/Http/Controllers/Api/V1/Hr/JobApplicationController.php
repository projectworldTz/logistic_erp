<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HireJobApplicationRequest;
use App\Http\Requests\Hr\StoreJobApplicationRequest;
use App\Http\Requests\Hr\UpdateJobApplicationStatusRequest;
use App\Http\Resources\JobApplicationResource;
use App\Models\JobApplication;
use App\Services\Hr\RecruitmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JobApplicationController extends Controller
{
    private const WITH = ['vacancy', 'candidate', 'interviews.interviewer'];

    public function index(Request $request)
    {
        return JobApplicationResource::collection(
            JobApplication::query()
                ->with(self::WITH)
                ->when($request->query('job_vacancy_id'), fn ($query, $id) => $query->where('job_vacancy_id', $id))
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest('applied_date')
                ->paginate(20)
        );
    }

    public function store(StoreJobApplicationRequest $request)
    {
        $application = JobApplication::query()->create([
            ...$request->validated(),
            'created_by' => Auth::id(),
        ])->refresh();

        return new JobApplicationResource($application->load(self::WITH));
    }

    public function show(JobApplication $jobApplication)
    {
        return new JobApplicationResource($jobApplication->load(self::WITH));
    }

    public function updateStatus(UpdateJobApplicationStatusRequest $request, JobApplication $jobApplication)
    {
        abort_if($jobApplication->status->value === 'hired', 409, 'A hired application\'s status cannot be changed directly.');

        $jobApplication->update($request->validated());

        return new JobApplicationResource($jobApplication->fresh()->load(self::WITH));
    }

    public function hire(HireJobApplicationRequest $request, JobApplication $jobApplication, RecruitmentService $service)
    {
        $employee = $service->hire($jobApplication, $request->validated());

        return response()->json([
            'data' => [
                'application' => new JobApplicationResource($jobApplication->fresh()->load(self::WITH)),
                'employee_id' => $employee->id,
            ],
        ], 201);
    }

    public function destroy(JobApplication $jobApplication)
    {
        abort_if($jobApplication->status->value === 'hired', 409, 'A hired application cannot be deleted.');

        $jobApplication->delete();

        return response()->json(status: 204);
    }
}
