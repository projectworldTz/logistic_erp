<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreInvoiceRequest;
use App\Http\Requests\Finance\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Company;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        return InvoiceResource::collection(
            Invoice::query()
                ->with(['customer'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreInvoiceRequest $request)
    {
        $invoice = Invoice::query()->create($request->validated())->refresh();

        return new InvoiceResource($invoice->load(['customer']));
    }

    public function show(Invoice $invoice)
    {
        return new InvoiceResource($invoice->load(['customer']));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $invoice->update($request->validated());

        return new InvoiceResource($invoice->load(['customer']));
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return response()->json(status: 204);
    }

    public function pdf(Invoice $invoice)
    {
        $company = Company::query()->firstOrFail();
        $invoice->load('customer');

        $logoBase64 = null;
        if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
            $path = Storage::disk('public')->path($company->logo_path);
            $mime = Storage::disk('public')->mimeType($company->logo_path);
            $logoBase64 = "data:{$mime};base64,".base64_encode(file_get_contents($path));
        }

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
            'logoBase64' => $logoBase64,
            'isReceipt' => $invoice->status === InvoiceStatus::Paid,
        ]);

        $prefix = $invoice->status === InvoiceStatus::Paid ? 'receipt' : 'invoice';

        return $pdf->download("{$prefix}-".($invoice->invoice_number ?? $invoice->id).'.pdf');
    }
}
