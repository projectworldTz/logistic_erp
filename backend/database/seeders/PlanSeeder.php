<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'For small forwarders getting started with digital operations.',
                'price_monthly' => 49,
                'price_yearly' => 490,
                'max_users' => 5,
                'max_branches' => 1,
                'features' => ['Core dashboard', 'Up to 5 users', '1 branch', 'Email support'],
                'sort_order' => 1,
            ],
            [
                'code' => 'professional',
                'name' => 'Professional',
                'description' => 'For growing logistics companies managing multiple branches.',
                'price_monthly' => 149,
                'price_yearly' => 1490,
                'max_users' => 25,
                'max_branches' => 5,
                'features' => ['Everything in Starter', 'Up to 25 users', '5 branches', 'Priority support'],
                'sort_order' => 2,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'For large clearing & forwarding operations at scale.',
                'price_monthly' => 399,
                'price_yearly' => 3990,
                'max_users' => null,
                'max_branches' => null,
                'features' => ['Everything in Professional', 'Unlimited users', 'Unlimited branches', 'Dedicated support'],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['code' => $plan['code']], $plan + ['currency' => 'USD', 'is_active' => true]);
        }
    }
}
