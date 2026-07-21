<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\UpdatePayrollSettingsRequest;
use App\Http\Resources\PayrollSettingsResource;
use App\Models\PayrollSettings;
use App\Support\Tenancy\TenantContext;

class PayrollSettingsController extends Controller
{
    private const WITH = ['statutoryRuleSet'];

    public function show(TenantContext $tenantContext)
    {
        $settings = $this->resolveSettings($tenantContext);

        return new PayrollSettingsResource($settings->load(self::WITH));
    }

    public function update(UpdatePayrollSettingsRequest $request, TenantContext $tenantContext)
    {
        $settings = $this->resolveSettings($tenantContext);
        $settings->update($request->validated());

        return new PayrollSettingsResource($settings->load(self::WITH));
    }

    private function resolveSettings(TenantContext $tenantContext): PayrollSettings
    {
        $settings = PayrollSettings::query()->firstOrCreate(['tenant_id' => $tenantContext->id()]);

        if ($settings->wasRecentlyCreated) {
            $settings->refresh();
            $settings->wasRecentlyCreated = false;
        }

        return $settings;
    }
}
