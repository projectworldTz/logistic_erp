<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
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
}
