<?php

namespace App\Models;

use App\Enums\ApprovalDecisionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_request_id',
        'step_position',
        'approver_role',
        'decided_by',
        'decision',
        'comment',
        'decided_at',
    ];

    protected $casts = [
        'step_position' => 'integer',
        'decision' => ApprovalDecisionType::class,
        'decided_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
