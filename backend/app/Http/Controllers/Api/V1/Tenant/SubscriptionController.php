<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ChangePlanRequest;
use App\Http\Resources\SubscriptionInvoiceResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;

class SubscriptionController extends Controller
{
    public function show()
    {
        $subscription = Subscription::query()->with('plan')->latest()->firstOrFail();

        return new SubscriptionResource($subscription);
    }

    public function invoices()
    {
        $subscription = Subscription::query()->latest()->firstOrFail();

        return SubscriptionInvoiceResource::collection(
            $subscription->invoices()->latest('period_start')->get()
        );
    }

    /**
     * Self-serve plan switching without a real payment gateway — the
     * subscription's plan/billing_cycle update immediately, and the next
     * scheduled invoice run bills the new plan's rate for the following
     * period. There is no live card collection to reject or retry here.
     */
    public function changePlan(ChangePlanRequest $request)
    {
        $subscription = Subscription::query()->latest()->firstOrFail();
        $plan = Plan::query()->where('code', $request->validated('plan_code'))->firstOrFail();

        $subscription->update([
            'plan_id' => $plan->id,
            'billing_cycle' => $request->validated('billing_cycle'),
        ]);

        return new SubscriptionResource($subscription->load('plan'));
    }
}
