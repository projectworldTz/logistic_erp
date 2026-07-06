<?php

namespace App\Http\Controllers\Api\V1\Demurrage;

use App\Enums\DemurrageChargeStatus;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Demurrage\WaiveDemurrageChargeRequest;
use App\Http\Resources\DemurrageChargeResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Container;
use App\Models\DemurrageCharge;
use App\Models\Invoice;
use App\Services\Demurrage\DemurrageCalculator;
use Illuminate\Http\Request;

class DemurrageChargeController extends Controller
{
    public function __construct(private readonly DemurrageCalculator $calculator) {}

    public function index(Request $request)
    {
        return DemurrageChargeResource::collection(
            DemurrageCharge::query()
                ->with(['container', 'customer'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function show(DemurrageCharge $charge)
    {
        return new DemurrageChargeResource($charge->load(['container', 'customer']));
    }

    public function calculate(Container $container)
    {
        $result = $this->calculator->calculate($container);

        $charge = DemurrageCharge::query()->create([
            'container_id' => $container->id,
            'customer_id' => $container->customer_id,
            'demurrage_rate_card_id' => $result['rate_card']?->id,
            'calculated_at' => now(),
            'dwell_days' => $result['dwell_days'],
            'free_days' => $result['free_days'],
            'chargeable_days' => $result['chargeable_days'],
            'amount' => $result['amount'],
            'currency' => $result['currency'],
            'breakdown' => $result['breakdown'],
            'status' => DemurrageChargeStatus::Pending,
        ]);

        return new DemurrageChargeResource($charge->load(['container', 'customer']));
    }

    public function waive(WaiveDemurrageChargeRequest $request, DemurrageCharge $charge)
    {
        abort_if($charge->status !== DemurrageChargeStatus::Pending, 422, 'Only pending charges can be waived.');

        $charge->update([
            'status' => DemurrageChargeStatus::Waived,
            'waived_reason' => $request->validated('reason'),
        ]);

        return new DemurrageChargeResource($charge->load(['container', 'customer']));
    }

    public function generateInvoice(DemurrageCharge $charge)
    {
        abort_if($charge->status !== DemurrageChargeStatus::Pending, 422, 'Only pending charges can be invoiced.');

        $invoice = Invoice::query()->create([
            'customer_id' => $charge->customer_id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => InvoiceStatus::Draft,
            'subtotal' => $charge->amount,
            'tax_amount' => 0,
            'total_amount' => $charge->amount,
            'currency' => $charge->currency,
            'notes' => "Demurrage charge for container {$charge->container->container_number} ({$charge->chargeable_days} chargeable days).",
        ]);

        $charge->update([
            'status' => DemurrageChargeStatus::Invoiced,
            'invoice_id' => $invoice->id,
        ]);

        return new InvoiceResource($invoice->load('customer'));
    }
}
