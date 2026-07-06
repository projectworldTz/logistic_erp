<?php

namespace App\Http\Controllers\Api\V1\Demurrage;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Container;
use App\Services\Demurrage\DemurrageCalculator;
use Illuminate\Http\JsonResponse;

class DemurrageDashboardController extends Controller
{
    public function __construct(private readonly DemurrageCalculator $calculator) {}

    /**
     * Live exception board: every container currently at port/warehouse
     * (not yet gated out) with its computed dwell time and accruing charge.
     */
    public function index(): JsonResponse
    {
        $rows = Container::query()
            ->with('customer')
            ->whereNotNull('gate_in_date')
            ->whereNull('gate_out_date')
            ->get()
            ->map(function (Container $container) {
                $result = $this->calculator->calculate($container);

                return [
                    'container_id' => $container->id,
                    'container_number' => $container->container_number,
                    'container_type' => $container->container_type,
                    'customer_id' => $container->customer_id,
                    'customer' => new CustomerResource($container->customer),
                    'gate_in_date' => $container->gate_in_date,
                    'rate_card_id' => $result['rate_card']?->id,
                    'rate_card_name' => $result['rate_card']?->name,
                    'currency' => $result['currency'],
                    'dwell_days' => $result['dwell_days'],
                    'free_days' => $result['free_days'],
                    'free_days_remaining' => $result['free_days_remaining'],
                    'chargeable_days' => $result['chargeable_days'],
                    'accrued_amount' => $result['amount'],
                    'risk_level' => $this->riskLevel($result),
                ];
            })
            ->sortByDesc('accrued_amount')
            ->values();

        return response()->json(['data' => $rows]);
    }

    private function riskLevel(array $result): string
    {
        if ($result['chargeable_days'] > 0) {
            return 'accruing';
        }

        if ($result['free_days_remaining'] <= 2) {
            return 'at_risk';
        }

        return 'within_free';
    }
}
