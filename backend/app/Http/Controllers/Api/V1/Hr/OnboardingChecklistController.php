<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\OnboardingChecklistStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreOnboardingTaskRequest;
use App\Http\Resources\OnboardingChecklistResource;
use App\Http\Resources\OnboardingTaskResource;
use App\Models\OnboardingChecklist;
use App\Models\OnboardingTask;
use Illuminate\Http\Request;

class OnboardingChecklistController extends Controller
{
    private const WITH = ['employee', 'tasks'];

    public function index(Request $request)
    {
        return OnboardingChecklistResource::collection(
            OnboardingChecklist::query()
                ->with(self::WITH)
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function show(OnboardingChecklist $onboardingChecklist)
    {
        return new OnboardingChecklistResource($onboardingChecklist->load(self::WITH));
    }

    public function storeTask(StoreOnboardingTaskRequest $request, OnboardingChecklist $onboardingChecklist)
    {
        $nextOrder = $onboardingChecklist->tasks()->max('sort_order') + 1;

        $task = $onboardingChecklist->tasks()->create([
            ...$request->validated(),
            'tenant_id' => $onboardingChecklist->tenant_id,
            'sort_order' => $nextOrder,
        ]);

        return new OnboardingTaskResource($task);
    }

    public function toggleTask(OnboardingTask $onboardingTask)
    {
        $onboardingTask->update([
            'is_completed' => ! $onboardingTask->is_completed,
            'completed_at' => $onboardingTask->is_completed ? null : now(),
        ]);

        $checklist = $onboardingTask->checklist;
        $allDone = $checklist->tasks()->where('is_completed', false)->doesntExist();

        $checklist->update([
            'status' => $allDone ? OnboardingChecklistStatus::Completed : OnboardingChecklistStatus::InProgress,
            'completed_at' => $allDone ? now() : null,
        ]);

        return new OnboardingTaskResource($onboardingTask->fresh());
    }

    public function destroyTask(OnboardingTask $onboardingTask)
    {
        $onboardingTask->delete();

        return response()->json(status: 204);
    }
}
