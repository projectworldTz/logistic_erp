<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreComplianceDocumentRequest;
use App\Http\Resources\ComplianceDocumentResource;
use App\Models\Customer;
use App\Models\CustomerComplianceDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComplianceDocumentController extends Controller
{
    public function index(Customer $customer)
    {
        return ComplianceDocumentResource::collection(
            $customer->complianceDocuments()->with('uploadedBy')->latest()->get()
        );
    }

    public function store(StoreComplianceDocumentRequest $request, Customer $customer)
    {
        $data = $request->validated();

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $data['file_path'] = $file->storeAs('compliance-documents', Str::random(20).'.'.$file->getClientOriginalExtension(), 'public');
        }

        $document = $customer->complianceDocuments()->create([
            ...$data,
            'uploaded_by' => Auth::id(),
        ]);

        return new ComplianceDocumentResource($document->load('uploadedBy'));
    }

    public function destroy(Customer $customer, CustomerComplianceDocument $complianceDocument)
    {
        abort_unless($complianceDocument->customer_id === $customer->id, 404);

        if ($complianceDocument->file_path) {
            Storage::disk('public')->delete($complianceDocument->file_path);
        }

        $complianceDocument->delete();

        return response()->json(status: 204);
    }
}
