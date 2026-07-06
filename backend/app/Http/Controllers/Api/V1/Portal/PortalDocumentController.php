<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\Uploads\DocumentUploadService;
use Illuminate\Http\Request;

class PortalDocumentController extends Controller
{
    public function index(Request $request)
    {
        return DocumentResource::collection(
            Document::query()
                ->where('customer_id', $request->user()->customer_id)
                ->with(['uploadedBy'])
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreDocumentRequest $request, DocumentUploadService $service)
    {
        // Never trust a client-supplied customer_id — always force it to the
        // authenticated portal user's own customer, regardless of what (if
        // anything) was submitted in the request body.
        $data = [...$request->validated(), 'customer_id' => $request->user()->customer_id];

        $document = $service->store($request->file('file'), $data);

        return new DocumentResource($document->load(['uploadedBy']));
    }
}
