<?php

namespace App\Http\Controllers\Api\V1\Quotations;

use App\Enums\ApprovalRequestStatus;
use App\Enums\QuotationStatus;
use App\Enums\WorkflowSubjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quotations\RejectQuotationRequest;
use App\Http\Requests\Quotations\StoreQuotationRequest;
use App\Http\Requests\Quotations\UpdateQuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Http\Resources\ShipmentResource;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\Shipment;
use App\Services\Quotations\QuotationItemService;
use App\Services\Workflow\ApprovalEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    private const WITH = ['customer', 'items', 'latestApprovalRequest.workflow.steps', 'latestApprovalRequest.decisions.decidedBy'];

    public function index(Request $request)
    {
        return QuotationResource::collection(
            Quotation::query()
                ->with(self::WITH)
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreQuotationRequest $request, QuotationItemService $itemService)
    {
        $data = $request->validated();
        $items = $data['items'] ?? null;
        unset($data['items']);
        $data['currency'] ??= Company::query()->value('currency') ?? 'TZS';

        $quotation = DB::transaction(function () use ($data, $items, $itemService) {
            $quotation = Quotation::query()->create($data)->refresh();

            if ($items) {
                $itemService->sync($quotation, $items);
            }

            return $quotation;
        });

        return new QuotationResource($quotation->load(self::WITH));
    }

    public function show(Quotation $quotation)
    {
        return new QuotationResource($quotation->load(self::WITH));
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation, QuotationItemService $itemService, ApprovalEngine $engine)
    {
        $data = $request->validated();
        $items = $data['items'] ?? null;
        unset($data['items']);

        if (($data['status'] ?? null) === QuotationStatus::Sent->value && $quotation->status === QuotationStatus::Draft) {
            $workflow = $engine->resolveWorkflow(WorkflowSubjectType::Quotation->value, (float) $quotation->total_amount);
            $latestRequest = $quotation->latestApprovalRequest;

            if ($workflow && $latestRequest?->status !== ApprovalRequestStatus::Approved) {
                abort(422, 'This quotation requires pricing approval before it can be sent — submit it for approval first.');
            }
        }

        DB::transaction(function () use ($data, $items, $quotation, $itemService) {
            $quotation->update($data);

            if ($items) {
                $itemService->sync($quotation, $items);
            }
        });

        return new QuotationResource($quotation->load(self::WITH));
    }

    public function destroy(Quotation $quotation)
    {
        $quotation->delete();

        return response()->json(status: 204);
    }

    /**
     * Create a Shipment from an accepted quotation, carrying over its
     * customer, direction, mode, and ports — a quotation can only become
     * one shipment (checked via the existing quotation_id relation).
     */
    public function convertToShipment(Quotation $quotation)
    {
        abort_unless($quotation->status === QuotationStatus::Accepted, 422, 'Only accepted quotations can be converted to a shipment.');
        abort_if($quotation->shipments()->exists(), 409, 'This quotation has already been converted to a shipment.');

        $shipment = Shipment::query()->create([
            'customer_id' => $quotation->customer_id,
            'quotation_id' => $quotation->id,
            'direction' => $quotation->direction->value,
            'mode' => $quotation->mode->value,
            'origin_port' => $quotation->origin_port,
            'destination_port' => $quotation->destination_port,
        ])->refresh();

        return new ShipmentResource($shipment->load(['customer', 'branch']));
    }

    /**
     * Request sign-off before sending a quotation to the customer. If a
     * pricing/discount approval workflow matches this quotation's total
     * amount, an ApprovalRequest is opened and the quotation stays in
     * draft until it clears every step; if no workflow is configured,
     * this is a no-op and the quotation can be sent immediately as before.
     */
    public function submit(Quotation $quotation, ApprovalEngine $engine)
    {
        abort_if($quotation->status !== QuotationStatus::Draft, 409, 'Only draft quotations can be submitted for approval.');
        abort_if($engine->findPendingRequestFor($quotation), 409, 'This quotation already has a pending approval request.');

        $engine->start($quotation, WorkflowSubjectType::Quotation->value, (float) $quotation->total_amount);

        return new QuotationResource($quotation->fresh()->load(self::WITH));
    }

    /**
     * Approve the quotation's current pending step. On the final step,
     * the quotation is automatically marked sent — approval is the
     * authorization to send it to the customer.
     */
    public function approve(Quotation $quotation, ApprovalEngine $engine)
    {
        $pending = $engine->findPendingRequestFor($quotation);
        abort_unless($pending, 404, 'This quotation has no pending approval request.');

        $decided = $engine->decide($pending, Auth::user(), true);

        if ($decided->status === ApprovalRequestStatus::Approved) {
            $quotation->update(['status' => QuotationStatus::Sent]);
        }

        return new QuotationResource($quotation->fresh()->load(self::WITH));
    }

    public function reject(RejectQuotationRequest $request, Quotation $quotation, ApprovalEngine $engine)
    {
        $pending = $engine->findPendingRequestFor($quotation);
        abort_unless($pending, 404, 'This quotation has no pending approval request.');

        $engine->decide($pending, Auth::user(), false, $request->validated('reason'));

        return new QuotationResource($quotation->fresh()->load(self::WITH));
    }
}
