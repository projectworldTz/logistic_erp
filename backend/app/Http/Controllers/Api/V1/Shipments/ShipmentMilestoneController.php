<?php

namespace App\Http\Controllers\Api\V1\Shipments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shipments\StoreTrackingEventRequest;
use App\Http\Resources\TrackingEventResource;
use App\Models\Shipment;
use Illuminate\Support\Facades\Auth;

class ShipmentMilestoneController extends Controller
{
    public function store(StoreTrackingEventRequest $request, Shipment $shipment)
    {
        $milestone = $shipment->milestones()->create([
            ...$request->validated(),
            'tenant_id' => $shipment->tenant_id,
            'recorded_by' => Auth::id(),
        ]);

        return new TrackingEventResource($milestone->load('recordedBy'));
    }
}
