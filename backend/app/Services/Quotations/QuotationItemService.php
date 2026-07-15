<?php

namespace App\Services\Quotations;

use App\Models\Quotation;

class QuotationItemService
{
    /**
     * Replace a quotation's line items and recompute subtotal/total_amount
     * from them — the server always derives the total from the line items
     * it just stored rather than trusting a client-supplied subtotal, since
     * the two could otherwise drift apart.
     */
    public function sync(Quotation $quotation, array $items): void
    {
        $quotation->items()->delete();

        $subtotal = 0;

        foreach (array_values($items) as $position => $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $amount = round($quantity * $unitPrice, 2);
            $subtotal += $amount;

            $quotation->items()->create([
                'position' => $position,
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $amount,
            ]);
        }

        $quotation->subtotal = $subtotal;
        $quotation->total_amount = $subtotal + (float) $quotation->tax_amount;
        $quotation->save();
    }
}
