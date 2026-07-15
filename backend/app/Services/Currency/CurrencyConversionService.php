<?php

namespace App\Services\Currency;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;

class CurrencyConversionService
{
    /**
     * Convert an amount between two currencies using the most recent rate
     * on or before the given date (defaults to today). Falls back to the
     * inverse of a rate stored the other way round (quote→base) if no
     * direct base→quote rate has been recorded, since a tenant typically
     * only ever records one direction for a given currency pair.
     */
    public function convert(float $amount, string $from, string $to, ?Carbon $asOf = null): ?float
    {
        if (strtoupper($from) === strtoupper($to)) {
            return round($amount, 2);
        }

        $rate = $this->resolveRate($from, $to, $asOf);

        return $rate === null ? null : round($amount * $rate, 2);
    }

    /**
     * The effective from→to rate as of the given date, direct or inverted.
     */
    public function resolveRate(string $from, string $to, ?Carbon $asOf = null): ?float
    {
        $asOf ??= now();

        $direct = $this->latestRate($from, $to, $asOf);

        if ($direct) {
            return (float) $direct->rate;
        }

        $inverse = $this->latestRate($to, $from, $asOf);

        if ($inverse && (float) $inverse->rate > 0) {
            return 1 / (float) $inverse->rate;
        }

        return null;
    }

    private function latestRate(string $base, string $quote, Carbon $asOf): ?ExchangeRate
    {
        return ExchangeRate::query()
            ->where('base_currency', strtoupper($base))
            ->where('quote_currency', strtoupper($quote))
            ->whereDate('rate_date', '<=', $asOf)
            ->orderByDesc('rate_date')
            ->first();
    }
}
