<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoginAttemptResource;
use App\Models\LoginAttempt;
use App\Support\Tenancy\TenantContext;

class LoginHistoryController extends Controller
{
    /**
     * Login attempts for this tenant. LoginAttempt has no TenantScope (it
     * must be insertable before tenant context resolves for unknown users),
     * so it is filtered by tenant_id here explicitly.
     */
    public function index(TenantContext $tenantContext)
    {
        return LoginAttemptResource::collection(
            LoginAttempt::query()
                ->where('tenant_id', $tenantContext->id())
                ->with('user')
                ->latest('created_at')
                ->paginate(30)
        );
    }
}
