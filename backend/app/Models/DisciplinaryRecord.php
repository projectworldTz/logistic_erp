<?php

namespace App\Models;

use App\Enums\DisciplinaryCategory;
use App\Enums\DisciplinaryRecordStatus;
use App\Enums\DisciplinarySeverity;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinaryRecord extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'incident_date',
        'category',
        'severity',
        'description',
        'action_taken',
        'issued_by',
        'status',
        'employee_response',
        'resolved_at',
        'created_by',
    ];

    protected $casts = [
        'category' => DisciplinaryCategory::class,
        'severity' => DisciplinarySeverity::class,
        'status' => DisciplinaryRecordStatus::class,
        'incident_date' => 'date',
        'resolved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
