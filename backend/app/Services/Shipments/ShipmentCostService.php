<?php

namespace App\Services\Shipments;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Models\Shipment;

class ShipmentCostService
{
    public function summarize(Shipment $shipment): array
    {
        $shipment->loadMissing(['invoices.customer', 'expenses.customer', 'expenses.creator']);

        $billedRevenue = $shipment->invoices
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Paid, InvoiceStatus::Overdue])
            ->sum('total_amount');

        $collectedRevenue = $shipment->invoices
            ->where('status', InvoiceStatus::Paid)
            ->sum('total_amount');

        $confirmedCost = $shipment->expenses
            ->whereIn('status', [ExpenseStatus::Approved, ExpenseStatus::Paid])
            ->sum('amount');

        $pendingCost = $shipment->expenses
            ->where('status', ExpenseStatus::Submitted)
            ->sum('amount');

        $profit = $billedRevenue - $confirmedCost;
        $marginPercent = $billedRevenue > 0 ? round(($profit / $billedRevenue) * 100, 2) : null;

        $costBreakdown = $shipment->expenses
            ->whereIn('status', [ExpenseStatus::Approved, ExpenseStatus::Paid])
            ->groupBy(fn ($expense) => $expense->category->value)
            ->map(fn ($group, $category) => [
                'category' => $category,
                'amount' => (float) $group->sum('amount'),
            ])
            ->values()
            ->all();

        $currency = $shipment->invoices->first()?->currency
            ?? $shipment->expenses->first()?->currency
            ?? 'TZS';

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
