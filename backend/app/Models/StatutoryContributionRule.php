<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatutoryContributionRule extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'statutory_rule_set_id',
        'code',
        'name',
        'employee_rate',
        'employer_rate',
        'min_base',
        'max_base',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'employee_rate' => 'decimal:3',
        'employer_rate' => 'decimal:3',
        'min_base' => 'decimal:2',
        'max_base' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(StatutoryRuleSet::class, 'statutory_rule_set_id');
    }
}
