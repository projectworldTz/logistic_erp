<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreInvoiceRequest;
use App\Http\Requests\Finance\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Tracking\ShipmentTrackingQrService;
use App\Support\Currency\CurrencyConverter;
use App\Support\Pdf\BrandColors;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        return InvoiceResource::collection(
            Invoice::query()
                ->with(['customer', 'branch'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreInvoiceRequest $request)
    {
        $data = $request->validated();
        $data['currency'] ??= Company::query()->value('currency') ?? 'TZS';

        $invoice = Invoice::query()->create($data)->refresh();

        return new InvoiceResource($invoice->load(['customer', 'branch']));
    }

    public function show(Invoice $invoice)
    {
        return new InvoiceResource($invoice->load(['customer', 'branch']));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $invoice->update($request->validated());

        return new InvoiceResource($invoice->load(['customer', 'branch']));
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return response()->json(status: 204);
    }

    public function pdf(Invoice $invoice, ShipmentTrackingQrService $qrService)
    {
        $company = Company::query()->firstOrFail();
        $invoice->load(['customer', 'shipment']);

        $logoBase64 = null;
        if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
            $path = Storage::disk('public')->path($company->logo_path);
            $mime = Storage::disk('public')->mimeType($company->logo_path);
            $logoBase64 = "data:{$mime};base64,".base64_encode(file_get_contents($path));
        }

        $trackingQrDataUri = $invoice->shipment
            ? 'data:image/svg+xml;base64,'.base64_encode($qrService->generateSvg($invoice->shipment->tracking_code))
            : null;

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
            'logoBase64' => $logoBase64,
            'isReceipt' => $invoice->status === InvoiceStatus::Paid,
            'trackingQrDataUri' => $trackingQrDataUri,
            'brand' => BrandColors::forCompany($company->primary_color),
            'displayCurrency' => $company->currency,
            'displaySubtotal' => CurrencyConverter::toSystemCurrency((float) $invoice->subtotal, $invoice->currency, $company),
            'displayTax' => CurrencyConverter::toSystemCurrency((float) $invoice->tax_amount, $invoice->currency, $company),
            'displayTotal' => CurrencyConverter::toSystemCurrency((float) $invoice->total_amount, $invoice->currency, $company),
        ]);

        $prefix = $invoice->status === InvoiceStatus::Paid ? 'receipt' : 'invoice';

        return $pdf->download("{$prefix}-".($invoice->invoice_number ?? $invoice->id).'.pdf');
    }
}
