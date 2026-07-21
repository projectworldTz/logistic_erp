<?php

namespace App\Services\Payroll;

use App\Enums\PayrollRunEmployeeStatus;
use App\Enums\SalaryPaymentBatchStatus;
use App\Enums\SalaryPaymentStatus;
use App\Models\PayrollRun;
use App\Models\SalaryPayment;
use App\Models\SalaryPaymentBatch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Builds one SalaryPaymentBatch per finalized run, snapshotting each
 * included employee's payment method and bank/mobile-money details at
 * that moment — the employee's live record could change later, but a
 * historical payment record must reflect what was actually used.
 *
 * This produces a payable batch and a CSV export for the tenant's bank
 * or mobile-money provider; it does not call any live payment gateway
 * (no such integration exists in this codebase), matching the plan's
 * explicit instruction not to claim one.
 */
class SalaryPaymentService
{
    public function generateForRun(PayrollRun $run): SalaryPaymentBatch
    {
        $existing = SalaryPaymentBatch::query()->where('payroll_run_id', $run->id)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($run) {
            $batch = SalaryPaymentBatch::query()->create([
                'tenant_id' => $run->tenant_id,
                'payroll_run_id' => $run->id,
                'payment_date' => $run->period->payment_date,
                'status' => SalaryPaymentBatchStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $runEmployees = $run->runEmployees()
                ->where('status', PayrollRunEmployeeStatus::Included)
                ->with('employee')
                ->get();

            $total = '0';

            foreach ($runEmployees as $runEmployee) {
                $employee = $runEmployee->employee;

                $batch->payments()->create([
                    'tenant_id' => $run->tenant_id,
                    'payroll_run_employee_id' => $runEmployee->id,
                    'employee_id' => $employee->id,
                    'amount' => $runEmployee->net_pay,
                    'payment_method' => $employee->preferred_payment_method ?? 'bank_transfer',
                    'bank_name' => $employee->bank_name,
                    'bank_account_number' => $employee->bank_account_number,
                    'mobile_money_provider' => $employee->mobile_money_provider,
                    'mobile_money_number' => $employee->mobile_money_number,
                    'status' => 'pending',
                ]);

                $total = PayrollMath::add($total, (string) $runEmployee->net_pay);
            }

            $batch->update(['total_amount' => PayrollMath::money($total)]);

            return $batch->fresh('payments.employee');
        });
    }

    public function markPaid(SalaryPayment $payment, ?string $reference): SalaryPayment
    {
        abort_if($payment->status !== SalaryPaymentStatus::Pending, 409, 'Only pending payments can be marked paid.');

        $payment->update(['status' => SalaryPaymentStatus::Paid, 'reference' => $reference, 'paid_at' => now()]);

        $this->syncBatchStatus($payment->batch);

        return $payment;
    }

    public function markFailed(SalaryPayment $payment, ?string $reference): SalaryPayment
    {
        abort_if($payment->status !== SalaryPaymentStatus::Pending, 409, 'Only pending payments can be marked failed.');

        $payment->update(['status' => SalaryPaymentStatus::Failed, 'reference' => $reference]);

        return $payment;
    }

    private function syncBatchStatus(SalaryPaymentBatch $batch): void
    {
        $allSettled = ! $batch->payments()->where('status', 'pending')->exists();

        if ($allSettled && $batch->status !== SalaryPaymentBatchStatus::Completed) {
            $batch->update(['status' => SalaryPaymentBatchStatus::Completed]);
        }
    }
}
