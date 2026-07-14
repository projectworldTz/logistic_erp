<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Models\WarehouseItem;
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

        $rows = $branches->map(fn (Branch $branch) => $this->buildRow($branch->id, $branch->name, $branch->is_default))
            ->push($this->buildRow(null, 'Unassigned', false))
            ->values();

        return response()->json(['data' => $rows]);
    }

    private function buildRow(?int $branchId, string $branchName, bool $isDefault): array
    {
        $shipments = Shipment::query()->where('branch_id', $branchId);
        $invoices = Invoice::query()->where('branch_id', $branchId);

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
            'revenue_paid' => (float) (clone $invoices)->where('status', 'paid')->sum('total_amount'),
            'revenue_outstanding' => (float) (clone $invoices)->whereIn('status', ['sent', 'overdue'])->sum('total_amount'),
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
