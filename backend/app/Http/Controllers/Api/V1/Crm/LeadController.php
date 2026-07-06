<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreLeadRequest;
use App\Http\Requests\Crm\UpdateLeadRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Services\Crm\LeadConversionService;

class LeadController extends Controller
{
    public function index()
    {
        return LeadResource::collection(
            Lead::query()->with('assignedTo')->latest()->paginate(20)
        );
    }

    public function store(StoreLeadRequest $request)
    {
        $lead = Lead::query()->create($request->validated())->refresh();

        return new LeadResource($lead);
    }

    public function show(Lead $lead)
    {
        return new LeadResource($lead->load(['assignedTo', 'convertedCustomer']));
    }

    public function update(UpdateLeadRequest $request, Lead $lead)
    {
        $lead->update($request->validated());

        return new LeadResource($lead);
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return response()->json(status: 204);
    }

    public function convert(Lead $lead, LeadConversionService $service)
    {
        $customer = $service->convert($lead);

        return new CustomerResource($customer);
    }
}
