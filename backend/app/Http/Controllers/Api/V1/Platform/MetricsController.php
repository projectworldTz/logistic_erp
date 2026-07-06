<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;

class MetricsController extends Controller
{
    /**
     * Platform-wide metrics. Revenue and storage usage are wired to real
     * billing/storage sources in a later pass; zeroed for now rather than
     * faked.
     */
    public function index()
    {
        return response()->json([
            'tenant_count' => Tenant::query()->count(),
            'active_tenant_count' => Tenant::query()->where('status', 'active')->count(),
            'trial_tenant_count' => Tenant::query()->where('status', 'trial')->count(),
            'active_users' => User::query()->where('status', 'active')->count(),
            'revenue_mtd' => 0,
            'storage_used_mb' => 0,
        ]);
    }
}
