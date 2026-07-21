<?php

namespace App\Services\Imports;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\CustomerStatus;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Imports\GenericHeadingRowImport;
use App\Models\AttendanceRecord;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Lead;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Bulk-creates records from an uploaded CSV/XLSX, one row at a time. A
 * failing row is skipped and reported (with its spreadsheet line number and
 * validation messages) rather than aborting the whole import — the same
 * partial-success shape most bulk importers use.
 */
class DataImportService
{
    public const IMPORTABLE_MODULES = ['customers', 'leads', 'attendance'];

    public function import(string $module, string $path): array
    {
        if (! in_array($module, self::IMPORTABLE_MODULES, true)) {
            throw new InvalidArgumentException("Unsupported import module: {$module}");
        }

        $importer = new GenericHeadingRowImport;
        Excel::import($importer, $path);

        $created = 0;
        $errors = [];

        foreach ($importer->rows as $index => $row) {
            $data = $row->toArray();

            if (collect($data)->filter()->isEmpty()) {
                continue; // skip fully blank rows
            }

            // The spreadsheet reader coerces numeric-looking cells (phone
            // numbers, postal codes) into int/float, which then fails a
            // plain 'string' validation rule — normalize everything back
            // to string first since every importable field here is textual.
            $data = array_map(
                fn ($value) => $value === null || $value === '' ? null : (string) $value,
                $data
            );

            $validator = Validator::make($data, $this->rulesFor($module));

            if ($validator->fails()) {
                $errors[] = [
                    'row' => $index + 2, // +1 for 0-index, +1 for the heading row itself
                    'messages' => $validator->errors()->all(),
                ];

                continue;
            }

            if (! $this->createRecord($module, $validator->validated())) {
                $errors[] = [
                    'row' => $index + 2,
                    'messages' => ['No matching employee, or an attendance record already exists for that employee and date.'],
                ];

                continue;
            }

            $created++;
        }

        return ['created' => $created, 'errors' => $errors];
    }

    private function rulesFor(string $module): array
    {
        $tenantId = app(TenantContext::class)->id();

        return match ($module) {
            'customers' => [
                'company_name' => ['required', 'string', 'max:255'],
                'industry' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'city' => ['nullable', 'string', 'max:100'],
                'country' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', new Enum(CustomerStatus::class)],
            ],
            'leads' => [
                'company_name' => ['required', 'string', 'max:255'],
                'contact_name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'source' => ['nullable', new Enum(LeadSource::class)],
                'status' => ['nullable', new Enum(LeadStatus::class)],
            ],
            'attendance' => [
                'employee_number' => ['required', 'string', Rule::exists('employees', 'employee_number')->where('tenant_id', $tenantId)],
                'date' => ['required', 'date'],
                'status' => ['nullable', new Enum(AttendanceStatus::class)],
                'check_in' => ['nullable', 'date'],
                'check_out' => ['nullable', 'date'],
            ],
        };
    }

    private function createRecord(string $module, array $data): bool
    {
        return match ($module) {
            'customers' => (bool) Customer::create([
                ...$data,
                'status' => $data['status'] ?? CustomerStatus::Active->value,
            ]),
            'leads' => (bool) Lead::create([
                ...$data,
                'status' => $data['status'] ?? LeadStatus::New->value,
                'source' => $data['source'] ?? LeadSource::Other->value,
            ]),
            'attendance' => $this->createAttendanceRecord($data),
        };
    }

    private function createAttendanceRecord(array $data): bool
    {
        $tenantId = app(TenantContext::class)->id();
        $employee = Employee::query()
            ->where('tenant_id', $tenantId)
            ->where('employee_number', $data['employee_number'])
            ->first();

        if (! $employee) {
            return false;
        }

        if (AttendanceRecord::query()->where('employee_id', $employee->id)->whereDate('date', $data['date'])->exists()) {
            return false;
        }

        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => $data['date'],
            'status' => $data['status'] ?? AttendanceStatus::Present->value,
            'check_in' => $data['check_in'] ?? null,
            'check_out' => $data['check_out'] ?? null,
            'source' => AttendanceSource::Import->value,
        ]);

        return true;
    }
}
