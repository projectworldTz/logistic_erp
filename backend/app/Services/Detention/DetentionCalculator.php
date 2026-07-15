<?php

namespace App\Services\Detention;

use App\Models\Container;
use App\Models\DetentionRateCard;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Detention charges for the time a customer holds a container *outside*
 * the port — from gate-out (container leaves the terminal) until it's
 * returned empty to the shipping line. This is the deliberate counterpart
 * to DemurrageCalculator, which measures dwell time *inside* the port
 * (gate-in to gate-out) instead — the two must never be computed from the
 * same date pair.
 */
class DetentionCalculator
{
    /**
     * Pick the most specific rate card for this container: one matching its
     * container_type first, falling back to the tenant's default rate card.
     */
    public function resolveRateCard(Container $container): ?DetentionRateCard
    {
        return DetentionRateCard::query()
            ->where('container_type', $container->container_type->value)
            ->first()
            ?? DetentionRateCard::query()->where('is_default', true)->first();
    }

    /**
     * Compute out-of-port time and the tiered detention amount for a
     * container as of a given moment (defaults to now). Does not persist
     * anything — callers decide whether to snapshot the result into a
     * DetentionCharge.
     */
    public function calculate(Container $container, ?DetentionRateCard $rateCard = null, ?CarbonInterface $asOf = null): array
    {
        $rateCard ??= $this->resolveRateCard($container);
        $asOf ??= Carbon::now();

        if (! $container->gate_out_date) {
            return $this->emptyResult($rateCard);
        }

        $endDate = $container->empty_return_date ?? $asOf->copy()->startOfDay();
        $detentionDays = max(0, (int) $container->gate_out_date->startOfDay()->diffInDays($endDate));

        if (! $rateCard) {
            return [
                'rate_card' => null,
                'currency' => 'USD',
                'detention_days' => $detentionDays,
                'free_days' => 0,
                'free_days_remaining' => 0,
                'chargeable_days' => 0,
                'amount' => 0.0,
                'breakdown' => [],
            ];
        }

        $freeDays = $rateCard->free_days;
        $chargeableDays = max(0, $detentionDays - $freeDays);

        [$amount, $breakdown] = $this->applyTiers($rateCard, $chargeableDays);

        return [
            'rate_card' => $rateCard,
            'currency' => $rateCard->currency,
            'detention_days' => $detentionDays,
            'free_days' => $freeDays,
            'free_days_remaining' => $freeDays - $detentionDays,
            'chargeable_days' => $chargeableDays,
            'amount' => round($amount, 2),
            'breakdown' => $breakdown,
        ];
    }

    private function applyTiers(DetentionRateCard $rateCard, int $chargeableDays): array
    {
        $remaining = $chargeableDays;
        $amount = 0.0;
        $breakdown = [];
        $lastRate = null;

        foreach ($rateCard->tiers as $tier) {
            if ($remaining <= 0) {
                break;
            }

            $tierSpan = $tier->to_day !== null ? max(0, $tier->to_day - $tier->from_day + 1) : $remaining;
            $daysInTier = min($remaining, $tierSpan);

            if ($daysInTier > 0) {
                $tierAmount = $daysInTier * (float) $tier->daily_rate;
                $amount += $tierAmount;
                $breakdown[] = [
                    'tier' => $tier->position,
                    'days' => $daysInTier,
                    'daily_rate' => (float) $tier->daily_rate,
                    'amount' => round($tierAmount, 2),
                ];
                $remaining -= $daysInTier;
            }

            $lastRate = (float) $tier->daily_rate;
        }

        // Days beyond the last configured tier continue at that tier's rate,
        // so an admin only needs to leave the final tier's to_day open-ended.
        if ($remaining > 0 && $lastRate !== null) {
            $tierAmount = $remaining * $lastRate;
            $amount += $tierAmount;
            $breakdown[] = [
                'tier' => 'overflow',
                'days' => $remaining,
                'daily_rate' => $lastRate,
                'amount' => round($tierAmount, 2),
            ];
        }

        return [$amount, $breakdown];
    }

    private function emptyResult(?DetentionRateCard $rateCard): array
    {
        return [
            'rate_card' => $rateCard,
            'currency' => $rateCard->currency ?? 'USD',
            'detention_days' => 0,
            'free_days' => $rateCard->free_days ?? 0,
            'free_days_remaining' => $rateCard->free_days ?? 0,
            'chargeable_days' => 0,
            'amount' => 0.0,
            'breakdown' => [],
        ];
    }
}
