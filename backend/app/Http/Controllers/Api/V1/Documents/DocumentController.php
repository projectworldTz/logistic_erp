<?php

namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\Uploads\DocumentUploadService;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        return DocumentResource::collection(
            Document::query()
                ->with(['customer', 'shipment', 'uploadedBy'])
                ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
                ->when($request->query('shipment_id'), fn ($query, $shipmentId) => $query->where('shipment_id', $shipmentId))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreDocumentRequest $request, DocumentUploadService $service)
    {
        $document = $service->store($request->file('file'), $request->validated());

        return new DocumentResource($document->load(['customer', 'shipment', 'uploadedBy']));
    }

    public function show(Document $document)
    {
        return new DocumentResource($document->load(['customer', 'shipment', 'uploadedBy']));
    }

    public function versions(Document $document)
    {
        $rootId = $document->root_document_id ?? $document->id;

        return DocumentResource::collection(
            Document::query()
                ->where('root_document_id', $rootId)
                ->orWhere('id', $rootId)
                ->with(['uploadedBy'])
                ->orderByDesc('version')
                ->get()
        );
    }

    public function destroy(Document $document, DocumentUploadService $service)
    {
        $service->delete($document);

        return response()->json(status: 204);
    }
}
