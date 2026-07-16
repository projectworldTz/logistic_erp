<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Console\Command;

/**
 * Bills every ACTIVE (post-trial) subscription for its current billing
 * period, across all tenants — no per-tenant TenantContext loop needed here
 * since every row is created with an explicit tenant_id already in hand, and
 * TenantScope applies no filter at all when no context is set.
 */
class GenerateSubscriptionInvoicesCommand extends Command
{
    protected $signature = 'subscriptions:generate-invoices';

    protected $description = 'Create a subscription invoice for each active subscription entering a new billing period, and flag unpaid ones past due as overdue';

    public function handle(): int
    {
        $created = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->with('plan')
            ->chunkById(50, function ($subscriptions) use (&$created) {
                foreach ($subscriptions as $subscription) {
                    if ($subscription->plan && $this->generateForCurrentPeriod($subscription)) {
                        $created++;
                    }
                }
            });

        $overdue = SubscriptionInvoice::query()
            ->where('status', SubscriptionInvoiceStatus::Pending->value)
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => SubscriptionInvoiceStatus::Overdue->value]);

        $this->info("Subscription invoices: {$created} created, {$overdue} marked overdue.");

        return self::SUCCESS;
    }

    private function generateForCurrentPeriod(Subscription $subscription): bool
    {
        [$periodStart, $periodEnd] = $this->currentPeriod($subscription);

        $exists = SubscriptionInvoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->exists();

        if ($exists) {
            return false;
        }

        $amount = $subscription->billing_cycle === 'yearly'
            ? $subscription->plan->price_yearly
            : $subscription->plan->price_monthly;

        SubscriptionInvoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'plan_name' => $subscription->plan->name,
            'amount' => $amount,
            'currency' => $subscription->plan->currency,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => $periodStart->copy()->addDays(7),
            'status' => SubscriptionInvoiceStatus::Pending->value,
        ]);

        return true;
    }

    /**
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function currentPeriod(Subscription $subscription): array
    {
        $addPeriod = fn ($date) => $subscription->billing_cycle === 'yearly'
            ? $date->copy()->addYear()
            : $date->copy()->addMonth();

        $periodStart = $subscription->starts_at->copy();
        $periodEnd = $addPeriod($periodStart);
        $now = now();

        while ($periodEnd->lte($now)) {
            $periodStart = $periodEnd->copy();
            $periodEnd = $addPeriod($periodStart);
        }

        return [$periodStart, $periodEnd];
    }
}
