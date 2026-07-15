<?php

namespace App\Http\Controllers\Api\V1\Detention;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Container;
use App\Services\Detention\DetentionCalculator;
use Illuminate\Http\JsonResponse;

class DetentionDashboardController extends Controller
{
    public function __construct(private readonly DetentionCalculator $calculator) {}

    /**
     * Live exception board: every container currently out with the customer
     * (gated out, not yet returned empty) with its computed out-of-port time
     * and accruing charge.
     */
    public function index(): JsonResponse
    {
        $rows = Container::query()
            ->with('customer')
            ->whereNotNull('gate_out_date')
            ->whereNull('empty_return_date')
            ->get()
            ->map(function (Container $container) {
                $result = $this->calculator->calculate($container);

                return [
                    'container_id' => $container->id,
                    'container_number' => $container->container_number,
                    'container_type' => $container->container_type,
                    'customer_id' => $container->customer_id,
                    'customer' => new CustomerResource($container->customer),
                    'gate_out_date' => $container->gate_out_date,
                    'rate_card_id' => $result['rate_card']?->id,
                    'rate_card_name' => $result['rate_card']?->name,
                    'currency' => $result['currency'],
                    'detention_days' => $result['detention_days'],
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
