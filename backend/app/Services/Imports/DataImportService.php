<?php

namespace App\Services\Imports;

use App\Enums\CustomerStatus;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Imports\GenericHeadingRowImport;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Support\Facades\Validator;
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
    public const IMPORTABLE_MODULES = ['customers', 'leads'];

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

            $this->createRecord($module, $validator->validated());
            $created++;
        }

        return ['created' => $created, 'errors' => $errors];
    }

    private function rulesFor(string $module): array
    {
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
        };
    }

    private function createRecord(string $module, array $data): void
    {
        match ($module) {
            'customers' => Customer::create([
                ...$data,
                'status' => $data['status'] ?? CustomerStatus::Active->value,
            ]),
            'leads' => Lead::create([
                ...$data,
                'status' => $data['status'] ?? LeadStatus::New->value,
                'source' => $data['source'] ?? LeadSource::Other->value,
            ]),
        };
    }
}
