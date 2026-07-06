<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\Audit\AuditLogger;

class TenantManagementController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index()
    {
        return TenantResource::collection(
            Tenant::query()
                ->with(['company', 'subscription.plan'])
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Tenant $tenant)
    {
        return new TenantResource($tenant->load(['company', 'subscription.plan', 'branches']));
    }

    public function suspend(Tenant $tenant)
    {
        $tenant->update(['status' => TenantStatus::Suspended, 'suspended_at' => now()]);

        $this->auditLogger->log(
            action: 'tenant.suspended',
            auditable: $tenant,
            tenantId: $tenant->id,
        );

        return new TenantResource($tenant);
    }

    public function activate(Tenant $tenant)
    {
        $tenant->update(['status' => TenantStatus::Active, 'suspended_at' => null]);

        $this->auditLogger->log(
            action: 'tenant.activated',
            auditable: $tenant,
            tenantId: $tenant->id,
        );

        return new TenantResource($tenant);
    }
}
