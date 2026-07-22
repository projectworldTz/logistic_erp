<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Models\WarehouseItem;
use App\Support\Currency\CurrencyConverter;
use Illuminate\Support\Collection;

class BranchRollupController extends Controller
{
    /**
     * Per-branch operational + financial rollup. Every dimension here is
     * nullable branch_id, so records never assigned to a branch are grouped
     * into a synthetic "Unassigned" row rather than silently dropped.
     */
    public function index()
    {
        $branches = Branch::query()->get();
        $company = Company::query()->firstOrFail();

        $rows = $branches->map(fn (Branch $branch) => $this->buildRow($branch->id, $branch->name, $branch->is_default, $company))
            ->push($this->buildRow(null, 'Unassigned', false, $company))
            ->values();

        return response()->json(['data' => $rows]);
    }

    private function buildRow(?int $branchId, string $branchName, bool $isDefault, Company $company): array
    {
        $shipments = Shipment::query()->where('branch_id', $branchId);
        $invoices = Invoice::query()->where('branch_id', $branchId);
        $toSystem = fn (float $amount, ?string $from) => CurrencyConverter::toSystemCurrency($amount, $from, $company);

        return [
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'is_default' => $isDefault,
            'employees_total' => Employee::query()->where('branch_id', $branchId)->count(),
            'vehicles_total' => Vehicle::query()->where('branch_id', $branchId)->count(),
            'warehouse_items_total' => WarehouseItem::query()->where('branch_id', $branchId)->count(),
            'shipments_total' => (clone $shipments)->count(),
            'shipments_by_status' => $this->countsByStatus((clone $shipments)),
            'invoices_total' => (clone $invoices)->count(),
            'revenue_paid' => round((clone $invoices)->where('status', 'paid')->get(['total_amount', 'currency'])->sum(fn ($i) => $toSystem((float) $i->total_amount, $i->currency)), 2),
            'revenue_outstanding' => round((clone $invoices)->whereIn('status', ['sent', 'overdue'])->get(['total_amount', 'currency'])->sum(fn ($i) => $toSystem((float) $i->total_amount, $i->currency)), 2),
        ];
    }

    private function countsByStatus($query): Collection
    {
        return $query
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count) => (int) $count);
    }
}
