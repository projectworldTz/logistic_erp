<?php

namespace App\Http\Controllers\Api\V1\ClientApi;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuotationResource;
use App\Models\CustomerApiKey;
use App\Models\Quotation;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        /** @var CustomerApiKey $apiKey */
        $apiKey = $request->attributes->get('client_api_key');

        return QuotationResource::collection(
            Quotation::query()
                ->where('customer_id', $apiKey->customer_id)
                ->with(['customer'])
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Request $request, int $quotation)
    {
        /** @var CustomerApiKey $apiKey */
        $apiKey = $request->attributes->get('client_api_key');

        $quotation = Quotation::query()
            ->where('customer_id', $apiKey->customer_id)
            ->with(['customer'])
            ->findOrFail($quotation);

        return new QuotationResource($quotation);
    }
}
