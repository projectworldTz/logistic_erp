<?php

namespace App\Http\Controllers\Api\V1\Shipments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shipments\StoreProofOfDeliveryRequest;
use App\Http\Resources\ProofOfDeliveryResource;
use App\Models\Shipment;
use App\Services\Shipments\ProofOfDeliveryService;

class ProofOfDeliveryController extends Controller
{
    public function show(Shipment $shipment)
    {
        abort_unless($shipment->proofOfDelivery, 404);

        return new ProofOfDeliveryResource($shipment->proofOfDelivery->load('capturedBy'));
    }

    public function store(StoreProofOfDeliveryRequest $request, Shipment $shipment, ProofOfDeliveryService $service)
    {
        $pod = $service->capture(
            $shipment,
            $request->validated(),
            $request->file('signature'),
            $request->file('photo'),
        );

        return new ProofOfDeliveryResource($pod->load('capturedBy'));
    }
}
