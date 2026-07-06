<?php

namespace App\Http\Requests\Accounting;

use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'entry_date' => ['sometimes', 'date'],
            'description' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
            'lines' => ['sometimes', 'array', 'min:2'],
            'lines.*.account_id' => ['required_with:lines', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)],
            'lines.*.debit' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.credit' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lines = $this->input('lines');

            if (! is_array($lines) || count($lines) < 2) {
                return;
            }

            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($lines as $index => $line) {
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);

                if (($debit > 0) === ($credit > 0)) {
                    $validator->errors()->add(
                        "lines.{$index}",
                        'Each line must have either a debit or a credit amount, not both or neither.'
                    );
                }

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                $validator->errors()->add(
                    'lines',
                    "Journal entry does not balance: total debit ({$totalDebit}) must equal total credit ({$totalCredit})."
                );
            }
        });
    }
}
