<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Contracts\IdentityVerificationProvider;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EmployeeIdentityVerification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class IdentityProviderSettingsController extends Controller
{
    /**
     * Read-only status/stats for the active identity provider — never
     * returns credentials, only what's already resolvable from
     * config/identity.php + this tenant's own verification history.
     */
    public function show(IdentityVerificationProvider $provider)
    {
        $tenantId = app(TenantContext::class)->id();

        $base = EmployeeIdentityVerification::query()->where('tenant_id', $tenantId);

        $total = (clone $base)->count();
        $successful = (clone $base)->whereIn('verification_status', ['verified', 'manually_verified'])->count();
        $failed = (clone $base)->whereIn('verification_status', [
            'failed', 'not_found', 'inactive', 'expired', 'provider_unavailable', 'rate_limited',
        ])->count();

        $lastSuccessful = (clone $base)
            ->whereIn('verification_status', ['verified', 'manually_verified'])
            ->latest('responded_at')->value('responded_at');

        $lastFailed = (clone $base)
            ->whereIn('verification_status', [
                'failed', 'not_found', 'inactive', 'expired', 'provider_unavailable', 'rate_limited',
            ])
            ->latest('responded_at')->value('responded_at');

        $averageResponseSeconds = (clone $base)
            ->whereNotNull('responded_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(SECOND, requested_at, responded_at)) as avg_seconds'))
            ->value('avg_seconds');

        return response()->json(['data' => [
            'provider_key' => $provider->key(),
            'provider_name' => $provider->displayName(),
            'is_live' => $provider->isLive(),
            'require_identity_verification_before_payroll' => (bool) Company::query()->value('require_identity_verification_before_payroll'),
            'stats' => [
                'total_requests' => $total,
                'successful_requests' => $successful,
                'failed_requests' => $failed,
                'last_successful_at' => $lastSuccessful,
                'last_failed_at' => $lastFailed,
                'average_response_seconds' => $averageResponseSeconds !== null ? round((float) $averageResponseSeconds, 2) : null,
            ],
        ]]);
    }
}
