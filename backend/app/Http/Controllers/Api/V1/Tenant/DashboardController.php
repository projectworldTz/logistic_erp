<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Enums\AccountType;
use App\Enums\ClearingStatus;
use App\Enums\ContainerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\ShipmentStatus;
use App\Enums\VehicleStatus;
use App\Enums\WarehouseItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardSummaryResource;
use App\Models\ClearingFile;
use App\Models\Container;
use App\Models\FreightBooking;
use App\Models\Invoice;
use App\Models\JournalEntryLine;
use App\Models\Shipment;
use App\Models\TenantDashboardSetting;
use App\Models\Vehicle;
use App\Models\WarehouseItem;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * This tenant's dashboard widget values, computed live from the ERP
     * modules now that they're built. Each widget is only computed and
     * returned if the requesting user has view access to the module it
     * draws from — a Driver, for example, has no business seeing company
     * Revenue/Expenses just because the dashboard route itself is ungated.
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        $settings = TenantDashboardSetting::query()->firstOrFail();
        $widgets = [];

        if ($user->can('freight.bookings.view')) {
            $widgets['daily_shipments'] = FreightBooking::query()->whereDate('created_at', today())->count();
        }

        if ($user->can('clearing.files.view')) {
            $widgets['pending_customs'] = ClearingFile::query()->whereNotIn('status', [
                ClearingStatus::Cleared, ClearingStatus::Delivered, ClearingStatus::Cancelled,
            ])->count();
        }

        if ($user->can('containers.items.view')) {
            $widgets['active_containers'] = Container::query()->whereNotIn('status', [
                ContainerStatus::Delivered, ContainerStatus::Returned,
            ])->count();
        }

        if ($user->can('finance.invoices.view')) {
            $widgets['revenue'] = (float) Invoice::query()->where('status', InvoiceStatus::Paid)->sum('total_amount');
            $widgets['outstanding_invoices'] = Invoice::query()->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])->count();
        }

        if ($user->can('accounting.accounts.view')) {
            $widgets['expenses'] = (float) JournalEntryLine::query()
                ->whereHas('account', fn ($query) => $query->where('type', AccountType::Expense))
                ->whereHas('journalEntry', fn ($query) => $query->where('status', JournalEntryStatus::Posted))
                ->sum('debit');
        }

        if ($user->can('fleet.vehicles.view')) {
            $widgets['fleet_status'] = [
                'active' => Vehicle::query()->where('status', VehicleStatus::Active)->count(),
                'maintenance' => Vehicle::query()->where('status', VehicleStatus::InMaintenance)->count(),
            ];
        }

        if ($user->can('shipments.items.view')) {
            $widgets['shipment_intelligence'] = $this->shipmentIntelligence();
        }

        if ($user->can('warehouse.items.view')) {
            $warehouseTotal = WarehouseItem::query()->count();
            $warehouseStored = WarehouseItem::query()->where('status', WarehouseItemStatus::Stored)->count();

            $widgets['warehouse_status'] = [
                'utilization_percent' => $warehouseTotal > 0 ? (int) round($warehouseStored / $warehouseTotal * 100) : 0,
            ];
        }

        $settings->widgets = $widgets;

        return new DashboardSummaryResource($settings);
    }

    /**
     * Snapshot the dashboard's Shipment Intelligence card draws on: how many
     * shipments are in flight, how many have actually reached the customer,
     * how many are already past their ETA (delayed) vs. approaching it
     * (near-deadline), and how broad the active customer base is. Customs
     * clearance speed is only included when the requesting user can also
     * see clearing data, since this widget itself is gated on shipments
     * permission alone.
     */
    private function shipmentIntelligence(): array
    {
        $activeStatuses = [ShipmentStatus::Delivered, ShipmentStatus::Cancelled];
        $completedStatuses = [ShipmentStatus::Arrived, ShipmentStatus::Delivered, ShipmentStatus::Cancelled];

        $active = Shipment::query()->whereNotIn('status', $activeStatuses)->count();
        $released = Shipment::query()->where('status', ShipmentStatus::Delivered)->count();

        $delayed = Shipment::query()
            ->whereNotNull('eta')
            ->where('eta', '<', now()->startOfDay())
            ->whereNotIn('status', $completedStatuses)
            ->count();

        $nearDeadline = Shipment::query()
            ->whereNotNull('eta')
            ->whereBetween('eta', [now()->startOfDay(), now()->addDays(3)->endOfDay()])
            ->whereNotIn('status', $completedStatuses)
            ->count();

        $customersServed = Shipment::query()->distinct('customer_id')->count('customer_id');

        $avgCustomsClearanceDays = null;

        if (request()->user()->can('clearing.files.view')) {
            $durations = ClearingFile::query()
                ->whereNotNull('cleared_date')
                ->get(['created_at', 'cleared_date'])
                ->map(fn ($file) => $file->created_at->diffInDays($file->cleared_date));

            $avgCustomsClearanceDays = $durations->isNotEmpty() ? round($durations->avg(), 1) : null;
        }

        return [
            'active' => $active,
            'released' => $released,
            'delayed' => $delayed,
            'near_deadline' => $nearDeadline,
            'customers_served' => $customersServed,
            'avg_customs_clearance_days' => $avgCustomsClearanceDays,
        ];
    }
}
