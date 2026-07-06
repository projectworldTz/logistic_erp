<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RegisterTenantRequest;
use App\Http\Resources\UserResource;
use App\Services\Tenancy\TenantProvisioningService;

class TenantRegistrationController extends Controller
{
    public function __construct(private readonly TenantProvisioningService $provisioningService) {}

    /**
     * Register a new tenant: creates the tenant, company, default branch,
     * owner user, RBAC roles, subscription, and billing profile in one
     * atomic operation, then logs the owner straight in.
     */
    public function store(RegisterTenantRequest $request)
    {
        $result = $this->provisioningService->provision(
            $request->validated(),
            $request->file('logo'),
        );

        $owner = $result['owner'];
        $token = $owner->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($owner),
        ], 201);
    }
}
