<?php

namespace App\Models;

use App\Enums\ComplianceDocumentType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerComplianceDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'uploaded_by',
        'document_type',
        'document_number',
        'issue_date',
        'expiry_date',
        'file_path',
        'notes',
    ];

    protected $casts = [
        'document_type' => ComplianceDocumentType::class,
        'issue_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return 'expired'|'expiring_soon'|'valid'|'no_expiry'
     */
    public function complianceStatus(): string
    {
        if (! $this->expiry_date) {
            return 'no_expiry';
        }

        if ($this->expiry_date->isPast()) {
            return 'expired';
        }

        if ($this->expiry_date->diffInDays(now(), absolute: true) <= 30) {
            return 'expiring_soon';
        }

        return 'valid';
    }
}
