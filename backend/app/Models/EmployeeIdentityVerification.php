<?php

namespace App\Models;

use App\Enums\IdentityDocumentType;
use App\Enums\IdentityVerificationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeIdentityVerification extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'identity_document_type',
        'identity_number_hash',
        'identity_number_masked',
        'identity_country_code',
        'provider',
        'provider_reference',
        'verification_status',
        'result_code',
        'result_message',
        'failure_reason',
        'requested_by',
        'confirmed_by',
        'rejected_by',
        'requested_at',
        'responded_at',
        'confirmed_at',
        'rejected_at',
        'request_metadata',
        'response_metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'identity_document_type' => IdentityDocumentType::class,
        'verification_status' => IdentityVerificationStatus::class,
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'request_metadata' => 'array',
        'response_metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function manualReviews(): HasMany
    {
        return $this->hasMany(EmployeeIdentityManualReview::class, 'verification_id');
    }
}
