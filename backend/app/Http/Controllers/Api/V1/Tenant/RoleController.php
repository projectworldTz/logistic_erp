<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index()
    {
        $tenantId = app(TenantContext::class)->id();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        return response()->json([
            'data' => Role::where('tenant_id', $tenantId)->orderBy('name')->pluck('name'),
        ]);
    }
}
