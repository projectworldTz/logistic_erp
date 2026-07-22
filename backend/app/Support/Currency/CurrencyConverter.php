<?php

namespace App\Support\Currency;

use App\Models\Company;

/**
 * Converts a stored amount into the company's current system currency for
 * display, using the owner-configured USD<->TZS rate. Storage stays
 * untouched — this is a read-time conversion only, so historical records
 * keep their original recorded currency and amount.
 */
class CurrencyConverter
{
    public static function toSystemCurrency(float $amount, ?string $fromCurrency, Company $company): float
    {
        $from = strtoupper($fromCurrency ?: $company->currency);
        $system = strtoupper($company->currency);

        if ($from === $system) {
            return $amount;
        }

        $rate = (float) ($company->usd_to_tzs_rate ?: 0);

        if ($rate <= 0) {
            return $amount;
        }

        if ($from === 'USD' && $system === 'TZS') {
            return $amount * $rate;
        }

        if ($from === 'TZS' && $system === 'USD') {
            return $amount / $rate;
        }

        return $amount;
    }
}
