<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreJobVacancyRequest;
use App\Http\Resources\JobVacancyResource;
use App\Models\JobVacancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JobVacancyController extends Controller
{
    private const WITH = ['department', 'designation'];

    public function index(Request $request)
    {
        return JobVacancyResource::collection(
            JobVacancy::query()
                ->with(self::WITH)
                ->withCount('applications')
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreJobVacancyRequest $request)
    {
        $vacancy = JobVacancy::query()->create([
            ...$request->validated(),
            'created_by' => Auth::id(),
        ])->refresh();

        return new JobVacancyResource($vacancy->load(self::WITH));
    }

    public function show(JobVacancy $jobVacancy)
    {
        return new JobVacancyResource($jobVacancy->load([...self::WITH, 'applications.candidate']));
    }

    public function update(StoreJobVacancyRequest $request, JobVacancy $jobVacancy)
    {
        $jobVacancy->update($request->validated());

        return new JobVacancyResource($jobVacancy->fresh()->load(self::WITH));
    }

    public function close(JobVacancy $jobVacancy)
    {
        $jobVacancy->update(['status' => 'closed']);

        return new JobVacancyResource($jobVacancy->fresh()->load(self::WITH));
    }

    public function destroy(JobVacancy $jobVacancy)
    {
        abort_if($jobVacancy->applications()->exists(), 409, 'This vacancy already has applications and cannot be deleted.');

        $jobVacancy->delete();

        return response()->json(status: 204);
    }
}
