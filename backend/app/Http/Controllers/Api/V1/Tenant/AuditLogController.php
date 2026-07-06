<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    /**
     * This tenant's audit trail (auto-scoped by TenantScope, since the
     * `tenant` middleware has bound TenantContext for this request).
     */
    public function index()
    {
        return AuditLogResource::collection(
            AuditLog::query()->with('user')->latest('created_at')->paginate(30)
        );
    }
}
