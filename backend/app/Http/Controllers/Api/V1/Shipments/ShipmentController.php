<?php

namespace App\Http\Controllers\Api\V1\Shipments;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shipments\StoreShipmentRequest;
use App\Http\Requests\Shipments\UpdateShipmentRequest;
use App\Http\Resources\ShipmentCostSummaryResource;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\Shipments\ShipmentCostService;
use App\Services\Shipments\SlaAlertService;
use App\Services\Tracking\QrCodeService;
use App\Services\Tracking\ShipmentTrackingQrService;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        return ShipmentResource::collection(
            Shipment::query()
                ->with(['customer', 'branch'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreShipmentRequest $request)
    {
        $shipment = Shipment::query()->create($request->validated())->refresh();

        return new ShipmentResource($shipment->load(['customer', 'branch']));
    }

    public function show(Shipment $shipment)
    {
        return new ShipmentResource($shipment->load(['customer', 'branch', 'milestones.recordedBy']));
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment)
    {
        $shipment->update($request->validated());

        return new ShipmentResource($shipment->load(['customer', 'branch']));
    }

    public function destroy(Shipment $shipment)
    {
        $shipment->delete();

        return response()->json(status: 204);
    }

    public function trackingQr(Shipment $shipment, ShipmentTrackingQrService $service)
    {
        return response($service->generateSvg($shipment->tracking_code), 200)
            ->header('Content-Type', 'image/svg+xml');
    }

    /**
     * SVG QR code linking to the public delivery-note verification page.
     * A delivery note only exists to verify once the shipment has
     * actually reached "delivered" — it reuses the shipment's existing
     * globally-unique tracking code rather than minting a new identifier.
     */
    public function deliveryNoteQr(Shipment $shipment, QrCodeService $service)
    {
        abort_if($shipment->status !== ShipmentStatus::Delivered, 404, 'This shipment has not been delivered yet.');

        $url = rtrim(config('app.frontend_url'), '/')."/verify/delivery-note/{$shipment->tracking_code}";

        return response($service->svg($url), 200)->header('Content-Type', 'image/svg+xml');
    }

    public function costSummary(Shipment $shipment, ShipmentCostService $service)
    {
        return new ShipmentCostSummaryResource($service->summarize($shipment));
    }

    /**
     * Manually trigger the SLA sweep for this tenant. The scheduled
     * `sla:check-shipments` command covers this in production, but this dev
     * environment doesn't run an OS-level cron, so this endpoint is the way
     * to exercise (and demonstrate) the alert pipeline on demand.
     */
    public function checkSla(SlaAlertService $service)
    {
        return response()->json($service->checkAndNotify());
    }
}
