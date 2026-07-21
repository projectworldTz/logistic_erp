<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StorePayrollComponentRequest;
use App\Http\Requests\Hr\UpdatePayrollComponentRequest;
use App\Http\Resources\PayrollComponentResource;
use App\Models\PayrollComponent;
use Illuminate\Http\Request;

class PayrollComponentController extends Controller
{
    private const WITH = ['branch', 'department'];

    public function index(Request $request)
    {
        return PayrollComponentResource::collection(
            PayrollComponent::query()
                ->with(self::WITH)
                ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
                ->when($request->query('type'), fn ($query, $type) => $query->where('type', $type))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }

    public function store(StorePayrollComponentRequest $request)
    {
        $component = PayrollComponent::query()->create($request->validated())->refresh();

        return new PayrollComponentResource($component->load(self::WITH));
    }

    public function show(PayrollComponent $payrollComponent)
    {
        return new PayrollComponentResource($payrollComponent->load(self::WITH));
    }

    public function update(UpdatePayrollComponentRequest $request, PayrollComponent $payrollComponent)
    {
        $payrollComponent->update($request->validated());

        return new PayrollComponentResource($payrollComponent->load(self::WITH));
    }

    public function destroy(PayrollComponent $payrollComponent)
    {
        $payrollComponent->delete();

        return response()->json(status: 204);
    }
}
