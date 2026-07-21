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
     *
     * $alreadyChargedDays is the number of chargeable days already captured
     * by prior charges for this container (pending, invoiced, or waived —
     * once a day has been charged once, it is never re-billed). The
     * returned chargeable_days/amount cover only the days beyond that, so
     * repeat calculations bill incrementally instead of re-charging the
     * whole dwell period every time.
     */
    public function calculate(Container $container, ?DemurrageRateCard $rateCard = null, ?CarbonInterface $asOf = null, int $alreadyChargedDays = 0): array
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
                'total_chargeable_days' => 0,
                'chargeable_days' => 0,
                'amount' => 0.0,
                'breakdown' => [],
            ];
        }

        $freeDays = $rateCard->free_days;
        $totalChargeableDays = max(0, $dwellDays - $freeDays);
        $newChargeableDays = max(0, $totalChargeableDays - $alreadyChargedDays);

        [$amount, $breakdown] = $this->applyTiers($rateCard, $alreadyChargedDays, $totalChargeableDays);

        return [
            'rate_card' => $rateCard,
            'currency' => $rateCard->currency,
            'dwell_days' => $dwellDays,
            'free_days' => $freeDays,
            'free_days_remaining' => $freeDays - $dwellDays,
            'total_chargeable_days' => $totalChargeableDays,
            'chargeable_days' => $newChargeableDays,
            'amount' => round($amount, 2),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Prices only the chargeable-day range (fromDay, toDay] — i.e. days
     * already accounted for (1..fromDay) are skipped — using each tier's
     * actual from_day/to_day range rather than treating "remaining days" as
     * always starting at day 1. This is what lets a repeat calculation
     * price just the newly-accrued days at the correct tier rate instead
     * of restarting the tiered pricing from day one.
     */
    private function applyTiers(DemurrageRateCard $rateCard, int $fromDay, int $toDay): array
    {
        $amount = 0.0;
        $breakdown = [];

        if ($toDay <= $fromDay) {
            return [$amount, $breakdown];
        }

        $lastRate = null;
        $maxCoveredDay = $fromDay;

        foreach ($rateCard->tiers as $tier) {
            $tierTo = $tier->to_day ?? $toDay;
            $overlapFrom = max($tier->from_day, $fromDay + 1);
            $overlapTo = min($tierTo, $toDay);

            if ($overlapTo >= $overlapFrom) {
                $days = $overlapTo - $overlapFrom + 1;
                $tierAmount = $days * (float) $tier->daily_rate;
                $amount += $tierAmount;
                $breakdown[] = [
                    'tier' => $tier->position,
                    'days' => $days,
                    'daily_rate' => (float) $tier->daily_rate,
                    'amount' => round($tierAmount, 2),
                ];
                $maxCoveredDay = max($maxCoveredDay, $overlapTo);
            }

            $lastRate = (float) $tier->daily_rate;
        }

        // Days beyond the last configured tier's range continue at that
        // tier's rate, so an admin only needs to leave the final tier's
        // to_day open-ended for unbounded pricing.
        if ($maxCoveredDay < $toDay && $lastRate !== null) {
            $days = $toDay - $maxCoveredDay;
            $tierAmount = $days * $lastRate;
            $amount += $tierAmount;
            $breakdown[] = [
                'tier' => 'overflow',
                'days' => $days,
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
            'total_chargeable_days' => 0,
            'chargeable_days' => 0,
            'amount' => 0.0,
            'breakdown' => [],
        ];
    }
}
