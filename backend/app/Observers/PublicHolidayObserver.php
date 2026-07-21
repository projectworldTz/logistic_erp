<?php

namespace App\Observers;

use App\Models\PublicHoliday;
use App\Services\Audit\AuditLogger;

class PublicHolidayObserver
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function created(PublicHoliday $holiday): void
    {
        $this->auditLogger->log('public_holiday.created', $holiday, newValues: $holiday->only(['date', 'name']), tenantId: $holiday->tenant_id);
    }

    public function deleted(PublicHoliday $holiday): void
    {
        $this->auditLogger->log('public_holiday.deleted', $holiday, oldValues: $holiday->only(['date', 'name']), tenantId: $holiday->tenant_id);
    }
}
