<?php

namespace App\Services\Uploads;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentUploadService
{
    public function store(UploadedFile $file, array $data): Document
    {
        $path = $file->storeAs('documents', Str::random(20).'.'.$file->getClientOriginalExtension(), 'public');

        return Document::query()->create([
            'customer_id' => $data['customer_id'] ?? null,
            'category' => $data['category'] ?? 'other',
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'uploaded_by' => Auth::id(),
            'description' => $data['description'] ?? null,
        ]);
    }

    public function delete(Document $document): void
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();
    }
}
