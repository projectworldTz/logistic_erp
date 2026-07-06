<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Scopes\TenantScope;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;

class ShipmentTrackingController extends Controller
{
    /**
     * Public, unauthenticated shipment lookup by tracking code — the one
     * piece of data a customer needs, independent of tenant/login context.
     * Deliberately excludes internal fields (customer_id, notes) and any
     * milestone not flagged customer-visible.
     */
    public function show(string $trackingCode): JsonResponse
    {
        $shipment = Shipment::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tracking_code', $trackingCode)
            ->with(['milestones' => fn ($query) => $query->where('is_customer_visible', true)])
            ->first();

        abort_if(! $shipment, 404);

        return response()->json([
            'data' => [
                'shipment_number' => $shipment->shipment_number,
                'tracking_code' => $shipment->tracking_code,
                'direction' => $shipment->direction,
                'mode' => $shipment->mode,
                'origin_port' => $shipment->origin_port,
                'destination_port' => $shipment->destination_port,
                'status' => $shipment->status,
                'etd' => $shipment->etd,
                'eta' => $shipment->eta,
                'milestones' => $shipment->milestones->map(fn ($milestone) => [
                    'event_type' => $milestone->event_type,
                    'location' => $milestone->location,
                    'occurred_at' => $milestone->occurred_at,
                ]),
            ],
        ]);
    }
}
