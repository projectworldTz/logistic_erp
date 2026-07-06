<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ClearingFile;
use App\Models\Container;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FreightBooking;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Models\WarehouseItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const RESULT_LIMIT = 5;

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();
        $results = [];

        if ($q === '') {
            return response()->json(['data' => (object) []]);
        }

        if ($user->can('crm.customers.view')) {
            $results['customers'] = Customer::query()
                ->where(fn (Builder $query) => $query
                    ->where('company_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%"))
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->map(fn (Customer $c) => ['id' => $c->id, 'label' => $c->company_name, 'path' => "/app/crm/customers/{$c->id}"]);
        }

        if ($user->can('crm.leads.view')) {
            $results['leads'] = Lead::query()
                ->where(fn (Builder $query) => $query
                    ->where('company_name', 'like', "%{$q}%")
                    ->orWhere('contact_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%"))
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->map(fn (Lead $l) => ['id' => $l->id, 'label' => $l->company_name, 'path' => '/app/crm']);
        }

        if ($user->can('quotations.items.view')) {
            $results['quotations'] = Quotation::query()
                ->where(fn (Builder $query) => $query
                    ->where('quotation_number', 'like', "%{$q}%")
                    ->orWhere('origin_port', 'like', "%{$q}%")
                    ->orWhere('destination_port', 'like', "%{$q}%"))
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->map(fn (Quotation $x) => ['id' => $x->id, 'label' => $x->quotation_number ?? "#{$x->id}", 'path' => '/app/quotations']);
        }

        if ($user->can('shipments.items.view')) {
            $results['shipments'] = Shipment::query()
                ->where(fn (Builder $query) => $query
                    ->where('shipment_number', 'like', "%{$q}%")
                    ->orWhere('bl_awb_number', 'like', "%{$q}%"))
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->map(fn (Shipment $x) => ['id' => $x->id, 'label' => $x->shipment_number ?? "#{$x->id}", 'path' => '/app/shipments']);
        }

        if ($user->can('clearing.files.view')) {
            $results['clearingFiles'] = ClearingFile::query()
                ->where(fn (Builder $query) => $query
                    ->where('reference_no', 'like', "%{$q}%")
                    ->orWhere('bl_awb_number', 'like', "%{$q}%")
                    ->orWhere('declaration_number', 'like', "%{$q}%"))
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->map(fn (ClearingFile $x) => ['id' => $x->id, 'label' => $x->reference_no ?? "#{$x->id}", 'path' => '/app/clearing']);
        }

        if ($user->can('freight.bookings.view')) {
            $results['freightBookings'] = FreightBooking::query()
                ->where(fn (Builder $query) => $query
                    ->where('reference_no', 'like', "%{$q}%")
                    ->orWhere('booking_number', 'like', "%{$q}%")
                    ->orWhere('carrier', 'like', "%{$q}%"))
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->map(fn (FreightBooking $x) => ['id' => $x->id, 'label' => $x->reference_no ?? "#{$x->id}", 'path' => '/app/freight']);
        }

        if ($user->can('containers.items.view')) {
            $results['containers'] = Container::query()
                ->where(fn (Builder $query) => $query
                    ->where('container_number', 'like', "%{$q}%")
                    ->orWhere('seal_number', 'like', "%{$q}%"))
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->map(fn (Container $x) => ['id' => $x->id, 'label' => $x->container_number, 'path' => '/app/containers']);
        }

        if ($user->can('warehouse.items.view')) {
            $results['warehouseItems'] = WarehouseItem::query()
                ->where(fn (Builder $query) => $query
                    ->where('reference_no', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%"))
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->map(fn (WarehouseItem $x) => ['id' => $x->id, 'label' => $x->reference_no ?? "#{$x->id}", 'path' => '/app/warehouse']);
        }

        if ($user->can('fleet.vehicles.view')) {
            $results['vehicles'] = Vehicle::query()
                ->where(fn (Builder $query) => $query
                    ->where('registration_number', 'like', "%{$q}%")
                    ->orWhere('make', 'like', "%{$q}%")
                    ->orWhere('model', 'like', "%{$q}%"))
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->map(fn (Vehicle $x) => ['id' => $x->id, 'label' => $x->registration_number, 'path' => '/app/fleet']);
        }

        if ($user->can('finance.invoices.view')) {
            $results['invoices'] = Invoice::query()
                ->where('invoice_number', 'like', "%{$q}%")
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->map(fn (Invoice $x) => ['id' => $x->id, 'label' => $x->invoice_number ?? "#{$x->id}", 'path' => '/app/finance']);
        }

        if ($user->can('documents.files.view')) {
            $results['documents'] = Document::query()
                ->where(fn (Builder $query) => $query
                    ->where('file_name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%"))
                ->limit(self::RESULT_LIMIT)
                ->get()
                ->map(fn (Document $x) => ['id' => $x->id, 'label' => $x->file_name, 'path' => '/app/documents']);
        }

        // Drop empty groups so the frontend only renders groups with results.
        $results = array_filter($results, fn ($group) => $group->isNotEmpty());

        return response()->json(['data' => (object) $results]);
    }
}
