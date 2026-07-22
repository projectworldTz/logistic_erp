<?php

namespace App\Services\Shipments;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Shipment;
use App\Support\Currency\CurrencyConverter;

class ShipmentCostService
{
    public function summarize(Shipment $shipment): array
    {
        $shipment->loadMissing(['invoices.customer', 'expenses.customer', 'expenses.creator']);

        $company = Company::query()->firstOrFail();
        $currency = $company->currency;

        $toSystem = fn (float $amount, ?string $from) => CurrencyConverter::toSystemCurrency($amount, $from, $company);

        $billedRevenue = $shipment->invoices
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Paid, InvoiceStatus::Overdue])
            ->sum(fn ($invoice) => $toSystem((float) $invoice->total_amount, $invoice->currency));

        $collectedRevenue = $shipment->invoices
            ->where('status', InvoiceStatus::Paid)
            ->sum(fn ($invoice) => $toSystem((float) $invoice->total_amount, $invoice->currency));

        $confirmedCost = $shipment->expenses
            ->whereIn('status', [ExpenseStatus::Approved, ExpenseStatus::Paid])
            ->sum(fn ($expense) => $toSystem((float) $expense->amount, $expense->currency));

        $pendingCost = $shipment->expenses
            ->where('status', ExpenseStatus::Submitted)
            ->sum(fn ($expense) => $toSystem((float) $expense->amount, $expense->currency));

        $profit = $billedRevenue - $confirmedCost;
        $marginPercent = $billedRevenue > 0 ? round(($profit / $billedRevenue) * 100, 2) : null;

        $costBreakdown = $shipment->expenses
            ->whereIn('status', [ExpenseStatus::Approved, ExpenseStatus::Paid])
            ->groupBy(fn ($expense) => $expense->category->value)
            ->map(fn ($group, $category) => [
                'category' => $category,
                'amount' => (float) $group->sum(fn ($expense) => $toSystem((float) $expense->amount, $expense->currency)),
            ])
            ->values()
            ->all();

        return [
            'currency' => $currency,
            'revenue' => [
                'billed' => (float) $billedRevenue,
                'collected' => (float) $collectedRevenue,
            ],
            'cost' => [
                'confirmed' => (float) $confirmedCost,
                'pending' => (float) $pendingCost,
            ],
            'profit' => (float) $profit,
            'margin_percent' => $marginPercent,
            'cost_breakdown' => $costBreakdown,
            'invoices' => $shipment->invoices->values()->all(),
            'expenses' => $shipment->expenses->values()->all(),
        ];
    }
}
