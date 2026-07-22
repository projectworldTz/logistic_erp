<?php

namespace App\Events\Identity;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class EmployeeIdentityOverridden
{
    use Dispatchable;

    public function __construct(
        public readonly Employee $employee,
        public readonly User $overriddenBy,
        public readonly string $reason,
    ) {}
}
