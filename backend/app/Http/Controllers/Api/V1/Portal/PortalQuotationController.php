<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuotationResource;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PortalQuotationController extends Controller
{
    public function index(Request $request)
    {
        return QuotationResource::collection(
            Quotation::query()
                ->where('customer_id', $request->user()->customer_id)
                ->with(['customer'])
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Request $request, int $quotation)
    {
        $quotation = Quotation::query()
            ->where('customer_id', $request->user()->customer_id)
            ->with(['customer'])
            ->findOrFail($quotation);

        return new QuotationResource($quotation);
    }

    public function approve(Request $request, int $quotation)
    {
        $quotation = Quotation::query()
            ->where('customer_id', $request->user()->customer_id)
            ->findOrFail($quotation);

        $this->assertPending($quotation);

        $quotation->update(['status' => QuotationStatus::Accepted]);

        return new QuotationResource($quotation->load('customer'));
    }

    public function reject(Request $request, int $quotation)
    {
        $quotation = Quotation::query()
            ->where('customer_id', $request->user()->customer_id)
            ->findOrFail($quotation);

        $this->assertPending($quotation);

        $quotation->update(['status' => QuotationStatus::Rejected]);

        return new QuotationResource($quotation->load('customer'));
    }

    private function assertPending(Quotation $quotation): void
    {
        if ($quotation->status !== QuotationStatus::Sent) {
            throw ValidationException::withMessages([
                'status' => 'Only quotations awaiting approval can be approved or rejected.',
            ]);
        }
    }
}
