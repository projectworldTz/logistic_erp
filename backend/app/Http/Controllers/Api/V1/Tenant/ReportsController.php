<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ClearingFile;
use App\Models\Company;
use App\Models\Container;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FreightBooking;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Models\WarehouseItem;
use App\Support\Currency\CurrencyConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportsController extends Controller
{
    /**
     * Cross-module reporting overview. Aggregates counts and status
     * breakdowns from every operational module — no new data of its own,
     * this is a read-only rollup over what the other modules already store.
     */
    public function overview(Request $request)
    {
        $branchId = $request->query('branch_id');
        $company = Company::query()->firstOrFail();
        $toSystem = fn (float $amount, ?string $from) => CurrencyConverter::toSystemCurrency($amount, $from, $company);

        $shipments = Shipment::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $invoices = Invoice::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        return response()->json([
            'branch_id' => $branchId ? (int) $branchId : null,
            'crm' => [
                'leads_total' => Lead::query()->count(),
                'leads_by_status' => $this->countsByStatus(Lead::class),
                'customers_total' => Customer::query()->count(),
            ],
            'quotations' => [
                'total' => Quotation::query()->count(),
                'by_status' => $this->countsByStatus(Quotation::class),
            ],
            'shipments' => [
                'total' => (clone $shipments)->count(),
                'by_status' => $this->countsByStatus(Shipment::class, $branchId),
            ],
            'clearing' => [
                'total' => ClearingFile::query()->count(),
                'by_status' => $this->countsByStatus(ClearingFile::class),
            ],
            'freight' => [
                'total' => FreightBooking::query()->count(),
                'by_status' => $this->countsByStatus(FreightBooking::class),
            ],
            'containers' => [
                'total' => Container::query()->count(),
                'by_status' => $this->countsByStatus(Container::class),
            ],
            'warehouse' => [
                'total' => WarehouseItem::query()->count(),
                'by_status' => $this->countsByStatus(WarehouseItem::class),
            ],
            'fleet' => [
                'total' => Vehicle::query()->count(),
                'by_status' => $this->countsByStatus(Vehicle::class),
            ],
            'finance' => [
                'invoices_total' => (clone $invoices)->count(),
                'invoices_by_status' => $this->countsByStatus(Invoice::class, $branchId),
                'outstanding_amount' => round((clone $invoices)->whereIn('status', ['sent', 'overdue'])->get(['total_amount', 'currency'])->sum(fn ($i) => $toSystem((float) $i->total_amount, $i->currency)), 2),
                'paid_amount' => round((clone $invoices)->where('status', 'paid')->get(['total_amount', 'currency'])->sum(fn ($i) => $toSystem((float) $i->total_amount, $i->currency)), 2),
            ],
            'accounting' => [
                'accounts_total' => Account::query()->count(),
                'journal_entries_by_status' => $this->countsByStatus(JournalEntry::class),
            ],
            'documents' => [
                'total' => Document::query()->count(),
            ],
        ]);
    }

    /**
     * Per-shipment revenue, cost and profit — the same billed/confirmed
     * classification as the single-shipment cost summary (Shipments
     * module), rolled up across every shipment that has at least one
     * invoice or expense attached.
     */
    public function profit(Request $request)
    {
        $branchId = $request->query('branch_id');
        $company = Company::query()->firstOrFail();
        $toSystem = fn (float $amount, ?string $from) => CurrencyConverter::toSystemCurrency($amount, $from, $company);

        $shipments = Shipment::query()
            ->with(['customer', 'invoices', 'expenses'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where(fn ($q) => $q->whereHas('invoices')->orWhereHas('expenses'))
            ->get();

        $rows = $shipments->map(function (Shipment $shipment) use ($toSystem) {
            $billedRevenue = (float) $shipment->invoices
                ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Paid, InvoiceStatus::Overdue])
                ->sum(fn ($invoice) => $toSystem((float) $invoice->total_amount, $invoice->currency));

            $confirmedCost = (float) $shipment->expenses
                ->whereIn('status', [ExpenseStatus::Approved, ExpenseStatus::Paid])
                ->sum(fn ($expense) => $toSystem((float) $expense->amount, $expense->currency));

            $profit = $billedRevenue - $confirmedCost;

            return [
                'shipment_id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'customer' => $shipment->customer?->company_name,
                'revenue' => $billedRevenue,
                'cost' => $confirmedCost,
                'profit' => $profit,
                'margin_percent' => $billedRevenue > 0 ? round(($profit / $billedRevenue) * 100, 2) : null,
            ];
        })->sortByDesc('profit')->values();

        return response()->json([
            'rows' => $rows,
            'totals' => [
                'revenue' => round($rows->sum('revenue'), 2),
                'cost' => round($rows->sum('cost'), 2),
                'profit' => round($rows->sum('profit'), 2),
            ],
        ]);
    }

    /**
     * Customs clearance throughput and duty/VAT totals — how fast files
     * clear, how much has been assessed, and where the workload sits
     * across customs offices and assessment states.
     */
    public function customs()
    {
        $files = ClearingFile::query()->get();

        $clearanceDurations = $files
            ->whereNotNull('cleared_date')
            ->map(fn (ClearingFile $file) => $file->created_at->diffInDays($file->cleared_date));

        return response()->json([
            'total_declarations' => $files->count(),
            'avg_clearance_days' => $clearanceDurations->isNotEmpty() ? round($clearanceDurations->avg(), 1) : null,
            'total_duty' => round((float) $files->sum('duty_amount'), 2),
            'total_vat' => round((float) $files->sum('vat_amount'), 2),
            'total_customs_value' => round((float) $files->sum('customs_value'), 2),
            'by_customs_office' => $files->whereNotNull('customs_office')->countBy('customs_office'),
            'by_assessment_status' => $files->countBy(fn (ClearingFile $file) => $file->assessment_status->value),
        ]);
    }

    /**
     * VAT collected (from paid invoices) and customs duty paid (from
     * cleared files), grouped by month — the pair of figures a tax filing
     * period needs.
     */
    public function tax(Request $request)
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : now()->subMonths(11)->startOfMonth();
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : now();
        $company = Company::query()->firstOrFail();
        $toSystem = fn (float $amount, ?string $sourceCurrency) => CurrencyConverter::toSystemCurrency($amount, $sourceCurrency, $company);

        $vatByMonth = Invoice::query()
            ->where('status', InvoiceStatus::Paid)
            ->whereBetween('issue_date', [$from, $to])
            ->get(['issue_date', 'tax_amount', 'currency'])
            ->groupBy(fn (Invoice $invoice) => $invoice->issue_date->format('Y-m'))
            ->map(fn ($group) => round((float) $group->sum(fn ($invoice) => $toSystem((float) $invoice->tax_amount, $invoice->currency)), 2))
            ->sortKeys();

        $dutyByMonth = ClearingFile::query()
            ->whereNotNull('cleared_date')
            ->whereBetween('cleared_date', [$from, $to])
            ->get(['cleared_date', 'duty_amount'])
            ->groupBy(fn (ClearingFile $file) => $file->cleared_date->format('Y-m'))
            ->map(fn ($group) => round((float) $group->sum('duty_amount'), 2))
            ->sortKeys();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'vat_collected_by_month' => $vatByMonth,
            'duty_paid_by_month' => $dutyByMonth,
            'totals' => [
                'vat_collected' => round($vatByMonth->sum(), 2),
                'duty_paid' => round($dutyByMonth->sum(), 2),
            ],
        ]);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function countsByStatus(string $model, ?string $branchId = null): array
    {
        return $model::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count) => (int) $count)
            ->toArray();
    }
}
