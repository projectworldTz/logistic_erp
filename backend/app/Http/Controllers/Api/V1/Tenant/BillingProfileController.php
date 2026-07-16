<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateBillingProfileRequest;
use App\Http\Resources\BillingProfileResource;
use App\Models\BillingProfile;

class BillingProfileController extends Controller
{
    public function show()
    {
        return new BillingProfileResource(BillingProfile::query()->firstOrFail());
    }

    public function update(UpdateBillingProfileRequest $request)
    {
        $profile = BillingProfile::query()->firstOrFail();
        $profile->update($request->validated());

        return new BillingProfileResource($profile);
    }
}
