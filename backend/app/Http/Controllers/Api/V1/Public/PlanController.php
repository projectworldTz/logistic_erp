<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;

class PlanController extends Controller
{
    /**
     * List active subscription plans, for the pricing page and the
     * tenant registration wizard's plan-selection step.
     */
    public function index()
    {
        return PlanResource::collection(
            Plan::query()->where('is_active', true)->orderBy('sort_order')->get()
        );
    }
}
