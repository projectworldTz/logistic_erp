<?php

namespace App\Http\Controllers\Api\V1\Detention;

use App\Enums\DetentionChargeStatus;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Detention\WaiveDetentionChargeRequest;
use App\Http\Resources\DetentionChargeResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Container;
use App\Models\DetentionCharge;
use App\Models\Invoice;
use App\Services\Detention\DetentionCalculator;
use Illuminate\Http\Request;

class DetentionChargeController extends Controller
{
    public function __construct(private readonly DetentionCalculator $calculator) {}

    public function index(Request $request)
    {
        return DetentionChargeResource::collection(
            DetentionCharge::query()
                ->with(['container', 'customer'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function show(DetentionCharge $charge)
    {
        return new DetentionChargeResource($charge->load(['container', 'customer']));
    }

    public function calculate(Container $container)
    {
        $result = $this->calculator->calculate($container);

        $charge = DetentionCharge::query()->create([
            'container_id' => $container->id,
            'customer_id' => $container->customer_id,
            'detention_rate_card_id' => $result['rate_card']?->id,
            'calculated_at' => now(),
            'detention_days' => $result['detention_days'],
            'free_days' => $result['free_days'],
            'chargeable_days' => $result['chargeable_days'],
            'amount' => $result['amount'],
            'currency' => $result['currency'],
            'breakdown' => $result['breakdown'],
            'status' => DetentionChargeStatus::Pending,
        ]);

        return new DetentionChargeResource($charge->load(['container', 'customer']));
    }

    public function waive(WaiveDetentionChargeRequest $request, DetentionCharge $charge)
    {
        abort_if($charge->status !== DetentionChargeStatus::Pending, 422, 'Only pending charges can be waived.');

        $charge->update([
            'status' => DetentionChargeStatus::Waived,
            'waived_reason' => $request->validated('reason'),
        ]);

        return new DetentionChargeResource($charge->load(['container', 'customer']));
    }

    public function generateInvoice(DetentionCharge $charge)
    {
        abort_if($charge->status !== DetentionChargeStatus::Pending, 422, 'Only pending charges can be invoiced.');

        $invoice = Invoice::query()->create([
            'customer_id' => $charge->customer_id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => InvoiceStatus::Draft,
            'subtotal' => $charge->amount,
            'tax_amount' => 0,
            'total_amount' => $charge->amount,
            'currency' => $charge->currency,
            'notes' => "Detention charge for container {$charge->container->container_number} ({$charge->chargeable_days} chargeable days).",
        ]);

        $charge->update([
            'status' => DetentionChargeStatus::Invoiced,
            'invoice_id' => $invoice->id,
        ]);

        return new InvoiceResource($invoice->load('customer'));
    }
}
