<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreApiKeyRequest;
use App\Http\Resources\CustomerApiKeyResource;
use App\Models\CustomerApiKey;
use Illuminate\Http\Request;

class PortalApiKeyController extends Controller
{
    public function index(Request $request)
    {
        return CustomerApiKeyResource::collection(
            CustomerApiKey::query()
                ->where('customer_id', $request->user()->customer_id)
                ->latest()
                ->get()
        );
    }

    public function store(StoreApiKeyRequest $request)
    {
        [$apiKey, $plaintext] = CustomerApiKey::generate(
            tenantId: $request->user()->tenant_id,
            customerId: $request->user()->customer_id,
            name: $request->validated('name'),
            createdBy: $request->user()->id,
        );

        return (new CustomerApiKeyResource($apiKey))
            ->additional(['api_key' => $plaintext])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, int $apiKey)
    {
        $apiKey = CustomerApiKey::query()
            ->where('customer_id', $request->user()->customer_id)
            ->findOrFail($apiKey);

        $apiKey->update(['revoked_at' => now()]);

        return response()->noContent();
    }
}
