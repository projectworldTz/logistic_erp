<?php

namespace App\Models;

use App\Enums\EmployeeDocumentStatus;
use App\Enums\EmployeeDocumentType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'document_type',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'issue_date',
        'expiry_date',
        'status',
        'notes',
        'uploaded_by',
        'verified_by',
        'verified_at',
        'version',
        'parent_document_id',
        'root_document_id',
    ];

    protected $casts = [
        'document_type' => EmployeeDocumentType::class,
        'status' => EmployeeDocumentStatus::class,
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function parentDocument(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'parent_document_id');
    }
}
