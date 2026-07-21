<?php

namespace App\Services\Uploads;

use App\Enums\EmployeeDocumentStatus;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores employee documents on the private 'local' disk (storage/app/private)
 * rather than the 'public' disk every other upload in this app uses — payroll
 * documents (national ID, contracts, medical certificates) are more sensitive,
 * so they're only ever reachable via a short-lived signed URL, never a plain
 * public path.
 */
class EmployeeDocumentUploadService
{
    public function store(Employee $employee, UploadedFile $file, array $data): EmployeeDocument
    {
        $path = $file->storeAs('employee-documents', Str::random(20).'.'.$file->getClientOriginalExtension(), 'local');

        $version = 1;
        $rootDocumentId = null;

        if (! empty($data['parent_document_id'])) {
            $parent = EmployeeDocument::query()->findOrFail($data['parent_document_id']);
            $rootDocumentId = $parent->root_document_id ?? $parent->id;
            $version = (int) EmployeeDocument::query()
                ->where(fn ($query) => $query->where('root_document_id', $rootDocumentId)->orWhere('id', $rootDocumentId))
                ->max('version') + 1;
        }

        return $employee->documents()->create([
            'tenant_id' => $employee->tenant_id,
            'document_type' => $data['document_type'],
            'status' => EmployeeDocumentStatus::PendingVerification->value,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'issue_date' => $data['issue_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'version' => $version,
            'parent_document_id' => $data['parent_document_id'] ?? null,
            'root_document_id' => $rootDocumentId,
            'uploaded_by' => Auth::id(),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function delete(EmployeeDocument $document): void
    {
        Storage::disk('local')->delete($document->file_path);
        $document->delete();
    }
}
