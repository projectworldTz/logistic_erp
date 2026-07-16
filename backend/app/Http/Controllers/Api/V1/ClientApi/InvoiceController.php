<?php

namespace App\Http\Controllers\Api\V1\ClientApi;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\CustomerApiKey;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        /** @var CustomerApiKey $apiKey */
        $apiKey = $request->attributes->get('client_api_key');

        return InvoiceResource::collection(
            Invoice::query()
                ->where('customer_id', $apiKey->customer_id)
                ->with(['customer'])
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Request $request, int $invoice)
    {
        /** @var CustomerApiKey $apiKey */
        $apiKey = $request->attributes->get('client_api_key');

        $invoice = Invoice::query()
            ->where('customer_id', $apiKey->customer_id)
            ->with(['customer'])
            ->findOrFail($invoice);

        return new InvoiceResource($invoice);
    }
}
