<?php

namespace App\Services\Reports;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Lead;
use App\Models\Payslip;
use App\Models\Quotation;
use App\Models\Shipment;
use Illuminate\Support\Str;

/**
 * Builds [headings, rows] pairs for each exportable report module. Shared by
 * the on-demand export endpoint (ReportExportController) and the scheduled
 * report email job (SendScheduledReports) so both stay in lockstep.
 */
class ReportBuilder
{
    /**
     * module => permission required to export/schedule it. Reuses each
     * module's existing "view" permission rather than introducing new RBAC
     * entries.
     */
    public const MODULE_PERMISSIONS = [
        'customers' => 'crm.customers.view',
        'leads' => 'crm.leads.view',
        'quotations' => 'quotations.items.view',
        'shipments' => 'shipments.items.view',
        'invoices' => 'finance.invoices.view',
        'expenses' => 'expenses.items.view',
        'profit' => 'reports.view',
        'employees' => 'hr.employees.view',
        'payslips' => 'hr.payslips.view.all',
        'leave_requests' => 'hr.leave.view',
    ];

    public function build(string $module): array
    {
        return $this->{'build'.Str::studly($module)}();
    }

    private function buildCustomers(): array
    {
        $rows = Customer::query()->with('assignedTo')->get()->map(fn (Customer $c) => [
            $c->id,
            $c->company_name,
            $c->industry,
            $c->email,
            $c->phone,
            $c->city,
            $c->country,
            $c->status->value,
            $c->assignedTo?->name,
            $c->created_at?->toDateTimeString(),
        ])->all();

        return [
            ['ID', 'Company Name', 'Industry', 'Email', 'Phone', 'City', 'Country', 'Status', 'Assigned To', 'Created At'],
            $rows,
        ];
    }

    private function buildLeads(): array
    {
        $rows = Lead::query()->with('assignedTo')->get()->map(fn (Lead $l) => [
            $l->id,
            $l->company_name,
            $l->contact_name,
            $l->email,
            $l->phone,
            $l->source->value,
            $l->status->value,
            $l->assignedTo?->name,
            $l->created_at?->toDateTimeString(),
        ])->all();

        return [
            ['ID', 'Company Name', 'Contact Name', 'Email', 'Phone', 'Source', 'Status', 'Assigned To', 'Created At'],
            $rows,
        ];
    }

    private function buildQuotations(): array
    {
        $rows = Quotation::query()->with('customer')->get()->map(fn (Quotation $q) => [
            $q->id,
            $q->quotation_number,
            $q->customer?->company_name,
            $q->direction->value,
            $q->mode->value,
            $q->origin_port,
            $q->destination_port,
            $q->status->value,
            $q->currency,
            (float) $q->total_amount,
            $q->issue_date?->toDateString(),
            $q->valid_until?->toDateString(),
        ])->all();

        return [
            ['ID', 'Quotation #', 'Customer', 'Direction', 'Mode', 'Origin', 'Destination', 'Status', 'Currency', 'Total Amount', 'Issue Date', 'Valid Until'],
            $rows,
        ];
    }

    private function buildShipments(): array
    {
        $rows = Shipment::query()->with('customer')->get()->map(fn (Shipment $s) => [
            $s->id,
            $s->shipment_number,
            $s->tracking_code,
            $s->customer?->company_name,
            $s->direction->value,
            $s->mode->value,
            $s->origin_port,
            $s->destination_port,
            $s->status->value,
            $s->etd?->toDateString(),
            $s->eta?->toDateString(),
        ])->all();

        return [
            ['ID', 'Shipment #', 'Tracking Code', 'Customer', 'Direction', 'Mode', 'Origin', 'Destination', 'Status', 'ETD', 'ETA'],
            $rows,
        ];
    }

    private function buildInvoices(): array
    {
        $rows = Invoice::query()->with('customer')->get()->map(fn (Invoice $i) => [
            $i->id,
            $i->invoice_number,
            $i->customer?->company_name,
            $i->status->value,
            $i->currency,
            (float) $i->subtotal,
            (float) $i->tax_amount,
            (float) $i->total_amount,
            $i->issue_date?->toDateString(),
            $i->due_date?->toDateString(),
        ])->all();

        return [
            ['ID', 'Invoice #', 'Customer', 'Status', 'Currency', 'Subtotal', 'Tax', 'Total', 'Issue Date', 'Due Date'],
            $rows,
        ];
    }

    private function buildExpenses(): array
    {
        $rows = Expense::query()->with(['customer', 'creator', 'approver'])->get()->map(fn (Expense $e) => [
            $e->id,
            $e->expense_number,
            $e->category->value,
            $e->description,
            $e->currency,
            (float) $e->amount,
            $e->is_billable ? 'Yes' : 'No',
            $e->customer?->company_name,
            $e->status->value,
            $e->creator?->name,
            $e->approver?->name,
            $e->expense_date?->toDateString(),
        ])->all();

        return [
            ['ID', 'Expense #', 'Category', 'Description', 'Currency', 'Amount', 'Billable', 'Customer', 'Status', 'Created By', 'Approved By', 'Expense Date'],
            $rows,
        ];
    }

    private function buildProfit(): array
    {
        $shipments = Shipment::query()->with(['customer', 'invoices', 'expenses'])
            ->whereHas('invoices')
            ->orWhereHas('expenses')
            ->get();

        $rows = $shipments->map(function (Shipment $shipment) {
            $revenue = (float) $shipment->invoices
                ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Paid, InvoiceStatus::Overdue])
                ->sum('total_amount');
            $cost = (float) $shipment->expenses
                ->whereIn('status', [ExpenseStatus::Approved, ExpenseStatus::Paid])
                ->sum('amount');

            return [
                $shipment->id,
                $shipment->shipment_number,
                $shipment->customer?->company_name,
                $revenue,
                $cost,
                $revenue - $cost,
            ];
        })->all();

        return [
            ['ID', 'Shipment #', 'Customer', 'Revenue', 'Cost', 'Profit'],
            $rows,
        ];
    }

    private function buildEmployees(): array
    {
        $rows = Employee::query()->with(['department', 'designation'])->get()->map(fn (Employee $e) => [
            $e->id,
            $e->employee_number,
            $e->name,
            $e->email,
            $e->phone,
            $e->department?->name,
            $e->designation?->name,
            $e->employment_type?->value,
            $e->status?->value,
            $e->hire_date?->toDateString(),
        ])->all();

        return [
            ['ID', 'Employee #', 'Name', 'Email', 'Phone', 'Department', 'Designation', 'Employment Type', 'Status', 'Hire Date'],
            $rows,
        ];
    }

    private function buildPayslips(): array
    {
        $rows = Payslip::query()->with(['employee', 'payrollRun.period'])->get()->map(fn (Payslip $p) => [
            $p->id,
            $p->payslip_number,
            $p->employee?->name,
            $p->payrollRun->period?->name,
            (float) $p->gross_pay,
            (float) $p->total_deductions,
            (float) $p->net_pay,
            (float) $p->ytd_net,
            $p->created_at?->toDateString(),
        ])->all();

        return [
            ['ID', 'Payslip #', 'Employee', 'Period', 'Gross Pay', 'Total Deductions', 'Net Pay', 'YTD Net', 'Generated At'],
            $rows,
        ];
    }

    private function buildLeaveRequests(): array
    {
        $rows = LeaveRequest::query()->with(['employee', 'leaveType'])->get()->map(fn (LeaveRequest $l) => [
            $l->id,
            $l->employee?->name,
            $l->leaveType?->name,
            $l->start_date?->toDateString(),
            $l->end_date?->toDateString(),
            (float) $l->days,
            $l->status->value,
        ])->all();

        return [
            ['ID', 'Employee', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Status'],
            $rows,
        ];
    }
}
