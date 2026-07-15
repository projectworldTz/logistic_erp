<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\ShipmentStatus;
use App\Models\Scopes\TenantScope;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;

class DeliveryNoteVerificationController extends Controller
{
    /**
     * Public, unauthenticated delivery-note verification, keyed off the
     * shipment's existing globally-unique tracking code — a delivery note
     * is only genuine (and only exists to verify) once the shipment has
     * actually reached "delivered".
     */
    public function show(string $trackingCode): JsonResponse
    {
        $shipment = Shipment::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tracking_code', $trackingCode)
            ->first();

        abort_if(! $shipment || $shipment->status !== ShipmentStatus::Delivered, 404);

        $deliveredMilestone = $shipment->milestones()
            ->where('is_customer_visible', true)
            ->where('event_type', 'delivered')
            ->latest('occurred_at')
            ->first();

        return response()->json([
            'data' => [
                'shipment_number' => $shipment->shipment_number,
                'tracking_code' => $shipment->tracking_code,
                'destination_port' => $shipment->destination_port,
                'status' => $shipment->status,
                'delivered_at' => $deliveredMilestone?->occurred_at,
            ],
        ]);
    }
}
