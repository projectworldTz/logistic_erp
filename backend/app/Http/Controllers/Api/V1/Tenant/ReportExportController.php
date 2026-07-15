<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends Controller
{
    /**
     * module => permission required to export it. Reuses each module's
     * existing "view" permission rather than introducing new RBAC entries.
     */
    private const MODULE_PERMISSIONS = [
        'customers' => 'crm.customers.view',
        'leads' => 'crm.leads.view',
        'quotations' => 'quotations.items.view',
        'shipments' => 'shipments.items.view',
        'invoices' => 'finance.invoices.view',
        'expenses' => 'expenses.items.view',
        'profit' => 'reports.view',
    ];

    public function export(Request $request, string $module)
    {
        abort_unless(array_key_exists($module, self::MODULE_PERMISSIONS), 404);
        abort_unless(Auth::user()->can(self::MODULE_PERMISSIONS[$module]), 403);

        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';
        $writerType = $format === 'xlsx' ? ExcelFormat::XLSX : ExcelFormat::CSV;

        [$headings, $rows] = $this->{'build'.ucfirst($module)}();
        $filename = "{$module}-".now()->format('Y-m-d').".{$format}";

        return Excel::download(new ArrayExport($headings, $rows), $filename, $writerType);
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
}
