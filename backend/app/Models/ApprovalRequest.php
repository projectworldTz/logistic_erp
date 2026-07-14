<?php

namespace App\Models;

use App\Enums\ApprovalRequestStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'subject_type',
        'subject_id',
        'approval_workflow_id',
        'current_step_position',
        'status',
        'created_by',
    ];

    protected $casts = [
        'current_step_position' => 'integer',
        'status' => ApprovalRequestStatus::class,
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class)->orderBy('step_position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function currentStep(): ?ApprovalWorkflowStep
    {
        return $this->workflow?->steps->firstWhere('position', $this->current_step_position);
    }
}
