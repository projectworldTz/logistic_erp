<?php

namespace App\Http\Controllers\Api\V1\ClientApi;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\CustomerApiKey;
use App\Models\Shipment;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        /** @var CustomerApiKey $apiKey */
        $apiKey = $request->attributes->get('client_api_key');

        return ShipmentResource::collection(
            Shipment::query()
                ->where('customer_id', $apiKey->customer_id)
                ->with(['customer'])
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Request $request, int $shipment)
    {
        /** @var CustomerApiKey $apiKey */
        $apiKey = $request->attributes->get('client_api_key');

        $shipment = Shipment::query()
            ->where('customer_id', $apiKey->customer_id)
            ->with(['customer', 'milestones' => fn ($query) => $query->where('is_customer_visible', true)])
            ->findOrFail($shipment);

        return new ShipmentResource($shipment);
    }
}
