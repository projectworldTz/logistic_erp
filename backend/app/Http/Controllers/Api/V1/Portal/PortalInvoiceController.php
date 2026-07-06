<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Company;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortalInvoiceController extends Controller
{
    public function index(Request $request)
    {
        return InvoiceResource::collection(
            Invoice::query()
                ->where('customer_id', $request->user()->customer_id)
                ->with(['customer'])
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Request $request, int $invoice)
    {
        $invoice = Invoice::query()
            ->where('customer_id', $request->user()->customer_id)
            ->with(['customer'])
            ->findOrFail($invoice);

        return new InvoiceResource($invoice);
    }

    public function pdf(Request $request, int $invoice)
    {
        $invoice = Invoice::query()
            ->where('customer_id', $request->user()->customer_id)
            ->with(['customer'])
            ->findOrFail($invoice);

        $company = Company::query()->firstOrFail();

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
