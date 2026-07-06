<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ClearingFile;
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

class ReportsController extends Controller
{
    /**
     * Cross-module reporting overview. Aggregates counts and status
     * breakdowns from every operational module — no new data of its own,
     * this is a read-only rollup over what the other modules already store.
     */
    public function overview()
    {
        return response()->json([
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
                'total' => Shipment::query()->count(),
                'by_status' => $this->countsByStatus(Shipment::class),
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
                'invoices_total' => Invoice::query()->count(),
                'invoices_by_status' => $this->countsByStatus(Invoice::class),
                'outstanding_amount' => Invoice::query()->whereIn('status', ['sent', 'overdue'])->sum('total_amount'),
                'paid_amount' => Invoice::query()->where('status', 'paid')->sum('total_amount'),
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
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function countsByStatus(string $model): array
    {
        return $model::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count) => (int) $count)
            ->toArray();
    }
}
