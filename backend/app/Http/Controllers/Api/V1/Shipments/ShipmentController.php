<?php

namespace App\Http\Controllers\Api\V1\Shipments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shipments\StoreShipmentRequest;
use App\Http\Requests\Shipments\UpdateShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        return ShipmentResource::collection(
            Shipment::query()
                ->with(['customer'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreShipmentRequest $request)
    {
        $shipment = Shipment::query()->create($request->validated())->refresh();

        return new ShipmentResource($shipment->load(['customer']));
    }

    public function show(Shipment $shipment)
    {
        return new ShipmentResource($shipment->load(['customer', 'milestones.recordedBy']));
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment)
    {
        $shipment->update($request->validated());

        return new ShipmentResource($shipment->load(['customer']));
    }

    public function destroy(Shipment $shipment)
    {
        $shipment->delete();

        return response()->json(status: 204);
    }
}
