<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantDashboardSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'widgets',
    ];

    protected $casts = [
        'widgets' => 'array',
    ];
}
