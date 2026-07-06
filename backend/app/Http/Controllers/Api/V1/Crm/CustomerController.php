<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCustomerRequest;
use App\Http\Requests\Crm\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;

class CustomerController extends Controller
{
    public function index()
    {
        return CustomerResource::collection(
            Customer::query()->with('assignedTo')->latest()->paginate(20)
        );
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::query()->create($request->validated())->refresh();

        return new CustomerResource($customer);
    }

    public function show(Customer $customer)
    {
        return new CustomerResource($customer->load(['assignedTo', 'contacts']));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json(status: 204);
    }
}
