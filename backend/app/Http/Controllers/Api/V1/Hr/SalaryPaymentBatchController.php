<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\UpdateSalaryPaymentRequest;
use App\Http\Resources\SalaryPaymentBatchResource;
use App\Http\Resources\SalaryPaymentResource;
use App\Models\PayrollRun;
use App\Models\SalaryPayment;
use App\Models\SalaryPaymentBatch;
use App\Services\Payroll\SalaryPaymentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalaryPaymentBatchController extends Controller
{
    public function store(PayrollRun $payrollRun, SalaryPaymentService $service)
    {
        abort_if($payrollRun->status->value !== 'finalized', 409, 'Only a finalized run can generate a salary payment batch.');

        $batch = $service->generateForRun($payrollRun);

        return new SalaryPaymentBatchResource($batch->load('payments.employee'));
    }

    public function show(SalaryPaymentBatch $salaryPaymentBatch)
    {
        return new SalaryPaymentBatchResource($salaryPaymentBatch->load('payments.employee'));
    }

    public function updatePayment(UpdateSalaryPaymentRequest $request, SalaryPayment $salaryPayment, SalaryPaymentService $service)
    {
        $data = $request->validated();

        $payment = $data['status'] === 'paid'
            ? $service->markPaid($salaryPayment, $data['reference'] ?? null)
            : $service->markFailed($salaryPayment, $data['reference'] ?? null);

        return new SalaryPaymentResource($payment->fresh('employee'));
    }

    public function exportCsv(SalaryPaymentBatch $salaryPaymentBatch): StreamedResponse
    {
        $payments = $salaryPaymentBatch->payments()->with('employee')->get();

        return response()->streamDownload(function () use ($payments) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Employee Number', 'Employee Name', 'Amount', 'Payment Method', 'Bank Name', 'Bank Account Number', 'Mobile Money Provider', 'Mobile Money Number', 'Status']);

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $payment->employee?->employee_number,
                    $payment->employee?->name,
                    $payment->amount,
                    $payment->payment_method,
                    $payment->bank_name,
                    $payment->bank_account_number,
                    $payment->mobile_money_provider,
                    $payment->mobile_money_number,
                    $payment->status->value,
                ]);
            }

            fclose($handle);
        }, "salary-payments-{$salaryPaymentBatch->batch_number}.csv", ['Content-Type' => 'text/csv']);
    }
}
