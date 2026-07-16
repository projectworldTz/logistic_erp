<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProofOfDeliveryResource;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\Tracking\ShipmentTrackingQrService;
use Illuminate\Http\Request;

class PortalShipmentController extends Controller
{
    public function index(Request $request)
    {
        return ShipmentResource::collection(
            Shipment::query()
                ->where('customer_id', $request->user()->customer_id)
                ->with(['customer'])
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Request $request, int $shipment)
    {
        $shipment = Shipment::query()
            ->where('customer_id', $request->user()->customer_id)
            ->with(['customer', 'milestones' => fn ($query) => $query->where('is_customer_visible', true)])
            ->findOrFail($shipment);

        return new ShipmentResource($shipment);
    }

    public function trackingQr(Request $request, int $shipment, ShipmentTrackingQrService $service)
    {
        $shipment = Shipment::query()
            ->where('customer_id', $request->user()->customer_id)
            ->findOrFail($shipment);

        return response($service->generateSvg($shipment->tracking_code), 200)
            ->header('Content-Type', 'image/svg+xml');
    }

    public function proofOfDelivery(Request $request, int $shipment)
    {
        $shipment = Shipment::query()
            ->where('customer_id', $request->user()->customer_id)
            ->with('proofOfDelivery')
            ->findOrFail($shipment);

        abort_unless($shipment->proofOfDelivery, 404);

        return new ProofOfDeliveryResource($shipment->proofOfDelivery);
    }
}
