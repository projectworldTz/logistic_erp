<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlanRequest;
use App\Http\Requests\Platform\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;

class PlanController extends Controller
{
    public function index()
    {
        return PlanResource::collection(Plan::query()->orderBy('sort_order')->get());
    }

    public function store(StorePlanRequest $request)
    {
        $plan = Plan::query()->create($request->validated() + ['currency' => $request->input('currency', 'USD')]);

        return new PlanResource($plan);
    }

    public function show(Plan $plan)
    {
        return new PlanResource($plan);
    }

    public function update(UpdatePlanRequest $request, Plan $plan)
    {
        $plan->update($request->validated());

        return new PlanResource($plan);
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();

        return response()->json(status: 204);
    }
}
