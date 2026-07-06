<?php

namespace App\Services\Demurrage;

use App\Models\Container;
use App\Models\DemurrageRateCard;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DemurrageCalculator
{
    /**
     * Pick the most specific rate card for this container: one matching its
     * container_type first, falling back to the tenant's default rate card.
     */
    public function resolveRateCard(Container $container): ?DemurrageRateCard
    {
        return DemurrageRateCard::query()
            ->where('container_type', $container->container_type->value)
            ->first()
            ?? DemurrageRateCard::query()->where('is_default', true)->first();
    }

    /**
     * Compute dwell time and the tiered demurrage amount for a container as
     * of a given moment (defaults to now). Does not persist anything —
     * callers decide whether to snapshot the result into a DemurrageCharge.
     */
    public function calculate(Container $container, ?DemurrageRateCard $rateCard = null, ?CarbonInterface $asOf = null): array
    {
        $rateCard ??= $this->resolveRateCard($container);
        $asOf ??= Carbon::now();

        if (! $container->gate_in_date) {
            return $this->emptyResult($rateCard);
        }

        $endDate = $container->gate_out_date ?? $asOf->copy()->startOfDay();
        $dwellDays = max(0, (int) $container->gate_in_date->startOfDay()->diffInDays($endDate));

        if (! $rateCard) {
            return [
                'rate_card' => null,
                'currency' => 'USD',
                'dwell_days' => $dwellDays,
                'free_days' => 0,
                'free_days_remaining' => 0,
                'chargeable_days' => 0,
                'amount' => 0.0,
                'breakdown' => [],
            ];
        }

        $freeDays = $rateCard->free_days;
        $chargeableDays = max(0, $dwellDays - $freeDays);

        [$amount, $breakdown] = $this->applyTiers($rateCard, $chargeableDays);

        return [
            'rate_card' => $rateCard,
            'currency' => $rateCard->currency,
            'dwell_days' => $dwellDays,
            'free_days' => $freeDays,
            'free_days_remaining' => $freeDays - $dwellDays,
            'chargeable_days' => $chargeableDays,
            'amount' => round($amount, 2),
            'breakdown' => $breakdown,
        ];
    }

    private function applyTiers(DemurrageRateCard $rateCard, int $chargeableDays): array
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

    private function emptyResult(?DemurrageRateCard $rateCard): array
    {
        return [
            'rate_card' => $rateCard,
            'currency' => $rateCard->currency ?? 'USD',
            'dwell_days' => 0,
            'free_days' => $rateCard->free_days ?? 0,
            'free_days_remaining' => $rateCard->free_days ?? 0,
            'chargeable_days' => 0,
            'amount' => 0.0,
            'breakdown' => [],
        ];
    }
}
