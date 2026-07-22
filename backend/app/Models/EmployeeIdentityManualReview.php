<?php

namespace App\Models;

use App\Enums\IdentityManualReviewStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeIdentityManualReview extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'verification_id',
        'submitted_by',
        'reviewed_by',
        'status',
        'reason',
        'supporting_document_type',
        'supporting_document_number',
        'supporting_document_path',
        'notes',
        'reviewer_notes',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'status' => IdentityManualReviewStatus::class,
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(EmployeeIdentityVerification::class, 'verification_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
