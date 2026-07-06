<?php

namespace App\Http\Controllers\Api\V1\Quotations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotations\StoreQuotationRequest;
use App\Http\Requests\Quotations\UpdateQuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Models\Quotation;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        return QuotationResource::collection(
            Quotation::query()
                ->with(['customer'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreQuotationRequest $request)
    {
        $quotation = Quotation::query()->create($request->validated())->refresh();

        return new QuotationResource($quotation->load(['customer']));
    }

    public function show(Quotation $quotation)
    {
        return new QuotationResource($quotation->load(['customer']));
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation)
    {
        $quotation->update($request->validated());

        return new QuotationResource($quotation->load(['customer']));
    }

    public function destroy(Quotation $quotation)
    {
        $quotation->delete();

        return response()->json(status: 204);
    }
}
