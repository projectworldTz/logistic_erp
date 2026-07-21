<?php

namespace App\Observers;

use App\Models\AttendanceRecord;
use App\Models\PublicHoliday;
use App\Services\Audit\AuditLogger;

class AttendanceRecordObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Weekend/holiday flags and late/early-departure minutes are always
     * derived, never trusted from client input — recomputed on every save
     * so an edited date/shift/check-in stays consistent.
     */
    public function saving(AttendanceRecord $record): void
    {
        if ($record->date) {
            $record->is_weekend = in_array($record->date->dayOfWeekIso, [6, 7], true);

            $record->is_holiday = PublicHoliday::query()
                ->where('tenant_id', $record->tenant_id)
                ->whereDate('date', $record->date)
                ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $record->employee?->branch_id))
                ->exists();
        }

        // Raw Unix-timestamp subtraction, not Carbon's diffInMinutes($other, $absolute) —
        // Carbon 3 changed diff methods to be signed by default and the sign convention
        // is easy to get backwards (bit us once already this session); a plain
        // "actual - scheduled" subtraction is unambiguous by construction.
        $shift = $record->shift;

        if ($shift && $record->check_in) {
            $scheduledStart = $record->date->copy()->setTimeFromTimeString($shift->start_time)->addMinutes($shift->grace_minutes);
            $record->late_minutes = max(0, intdiv($record->check_in->getTimestamp() - $scheduledStart->getTimestamp(), 60));
        } else {
            $record->late_minutes = null;
        }

        if ($shift && $record->check_out) {
            $scheduledEnd = $record->date->copy()->setTimeFromTimeString($shift->end_time);
            $record->early_departure_minutes = max(0, intdiv($scheduledEnd->getTimestamp() - $record->check_out->getTimestamp(), 60));
        } else {
            $record->early_departure_minutes = null;
        }
    }

    public function created(AttendanceRecord $record): void
    {
        $this->auditLogger->log(
            action: 'attendance_record.created',
            auditable: $record,
            newValues: $record->only(['employee_id', 'date', 'status']),
            tenantId: $record->tenant_id,
        );
    }

    public function updated(AttendanceRecord $record): void
    {
        $this->auditLogger->log(
            action: 'attendance_record.updated',
            auditable: $record,
            oldValues: $record->getOriginal(),
            newValues: $record->getChanges(),
            tenantId: $record->tenant_id,
        );
    }

    public function deleted(AttendanceRecord $record): void
    {
        $this->auditLogger->log(
            action: 'attendance_record.deleted',
            auditable: $record,
            oldValues: $record->only(['employee_id', 'date']),
            tenantId: $record->tenant_id,
        );
    }
}
