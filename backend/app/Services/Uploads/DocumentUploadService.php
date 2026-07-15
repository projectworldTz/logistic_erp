<?php

namespace App\Services\Uploads;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentUploadService
{
    /**
     * When parent_document_id is given, the upload becomes the next version
     * in that document's lineage (version + 1, sharing the lineage's
     * root_document_id) rather than a brand-new, unrelated document.
     */
    public function store(UploadedFile $file, array $data): Document
    {
        $path = $file->storeAs('documents', Str::random(20).'.'.$file->getClientOriginalExtension(), 'public');

        $version = 1;
        $rootDocumentId = null;

        if (! empty($data['parent_document_id'])) {
            $parent = Document::query()->findOrFail($data['parent_document_id']);
            $rootDocumentId = $parent->root_document_id ?? $parent->id;
            $version = (int) Document::query()
                ->where(fn ($query) => $query->where('root_document_id', $rootDocumentId)->orWhere('id', $rootDocumentId))
                ->max('version') + 1;
        }

        $document = Document::query()->create([
            'customer_id' => $data['customer_id'] ?? null,
            'shipment_id' => $data['shipment_id'] ?? null,
            'category' => $data['category'] ?? 'other',
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'version' => $version,
            'parent_document_id' => $data['parent_document_id'] ?? null,
            'root_document_id' => $rootDocumentId,
            'uploaded_by' => Auth::id(),
            'description' => $data['description'] ?? null,
        ]);

        if ($rootDocumentId === null) {
            $document->update(['root_document_id' => $document->id]);
        }

        return $document;
    }

    public function delete(Document $document): void
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();
    }
}
