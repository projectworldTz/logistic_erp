<?php

namespace App\Models;

use App\Enums\ExitRecordStatus;
use App\Enums\ExitType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitRecord extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'exit_type',
        'notice_date',
        'last_working_date',
        'reason',
        'exit_interview_notes',
        'status',
        'assets_cleared',
        'handover_completed',
        'unused_leave_days',
        'leave_payout_amount',
        'outstanding_loan_balance',
        'outstanding_advance_balance',
        'final_settlement_amount',
        'initiated_by',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'exit_type' => ExitType::class,
        'status' => ExitRecordStatus::class,
        'notice_date' => 'date',
        'last_working_date' => 'date',
        'assets_cleared' => 'boolean',
        'handover_completed' => 'boolean',
        'unused_leave_days' => 'decimal:2',
        'leave_payout_amount' => 'decimal:2',
        'outstanding_loan_balance' => 'decimal:2',
        'outstanding_advance_balance' => 'decimal:2',
        'final_settlement_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
