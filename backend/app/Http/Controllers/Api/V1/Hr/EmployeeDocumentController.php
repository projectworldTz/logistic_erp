<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\EmployeeDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RejectEmployeeDocumentRequest;
use App\Http\Requests\Hr\StoreEmployeeDocumentRequest;
use App\Http\Resources\EmployeeDocumentResource;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\Audit\AuditLogger;
use App\Services\Uploads\EmployeeDocumentUploadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EmployeeDocumentController extends Controller
{
    public function index(Employee $employee)
    {
        return EmployeeDocumentResource::collection(
            $employee->documents()->with(['uploadedBy', 'verifiedBy'])->latest()->get()
        );
    }

    public function store(StoreEmployeeDocumentRequest $request, Employee $employee, EmployeeDocumentUploadService $service)
    {
        $document = $service->store($employee, $request->file('file'), $request->validated());

        return new EmployeeDocumentResource($document->load(['uploadedBy']));
    }

    public function show(EmployeeDocument $employeeDocument)
    {
        return new EmployeeDocumentResource($employeeDocument->load(['uploadedBy', 'verifiedBy']));
    }

    public function verify(EmployeeDocument $employeeDocument, AuditLogger $auditLogger)
    {
        $employeeDocument->update([
            'status' => EmployeeDocumentStatus::Verified->value,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        $auditLogger->log(
            action: 'employee_document.verified',
            auditable: $employeeDocument,
            newValues: ['status' => EmployeeDocumentStatus::Verified->value],
            tenantId: $employeeDocument->tenant_id,
        );

        return new EmployeeDocumentResource($employeeDocument->load(['uploadedBy', 'verifiedBy']));
    }

    public function reject(RejectEmployeeDocumentRequest $request, EmployeeDocument $employeeDocument, AuditLogger $auditLogger)
    {
        $employeeDocument->update([
            'status' => EmployeeDocumentStatus::Rejected->value,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'notes' => trim(($employeeDocument->notes ? $employeeDocument->notes."\n" : '')."Rejected: {$request->string('reason')}"),
        ]);

        $auditLogger->log(
            action: 'employee_document.rejected',
            auditable: $employeeDocument,
            newValues: ['status' => EmployeeDocumentStatus::Rejected->value, 'reason' => $request->string('reason')->toString()],
            tenantId: $employeeDocument->tenant_id,
        );

        return new EmployeeDocumentResource($employeeDocument->load(['uploadedBy', 'verifiedBy']));
    }

    public function destroy(EmployeeDocument $employeeDocument, EmployeeDocumentUploadService $service)
    {
        $service->delete($employeeDocument);

        return response()->json(status: 204);
    }

    /**
     * Only reachable via the short-lived signed URL EmployeeDocumentResource
     * generates — enforced by the `signed` route middleware, not a bearer
     * token, since this needs to work from a plain browser download link.
     */
    public function download(EmployeeDocument $employeeDocument)
    {
        return Storage::disk('local')->download($employeeDocument->file_path, $employeeDocument->file_name);
    }
}
