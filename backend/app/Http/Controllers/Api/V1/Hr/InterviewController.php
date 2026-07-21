<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\CompleteInterviewRequest;
use App\Http\Requests\Hr\StoreInterviewRequest;
use App\Http\Resources\InterviewResource;
use App\Models\Interview;
use Illuminate\Support\Facades\Auth;

class InterviewController extends Controller
{
    private const WITH = ['interviewer', 'jobApplication.candidate'];

    public function store(StoreInterviewRequest $request)
    {
        $interview = Interview::query()->create([
            ...$request->validated(),
            'created_by' => Auth::id(),
        ])->refresh();

        return new InterviewResource($interview->load(self::WITH));
    }

    public function show(Interview $interview)
    {
        return new InterviewResource($interview->load(self::WITH));
    }

    public function complete(CompleteInterviewRequest $request, Interview $interview)
    {
        $interview->update($request->validated());

        return new InterviewResource($interview->fresh()->load(self::WITH));
    }

    public function destroy(Interview $interview)
    {
        $interview->delete();

        return response()->json(status: 204);
    }
}
