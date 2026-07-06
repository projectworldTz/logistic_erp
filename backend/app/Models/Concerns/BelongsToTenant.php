<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Support\Tenancy\TenantContext;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $context = app(TenantContext::class);

                if ($context->hasTenant()) {
                    $model->tenant_id = $context->id();
                }
            }
        });
    }
}
