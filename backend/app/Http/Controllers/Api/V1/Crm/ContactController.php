<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreContactRequest;
use App\Http\Requests\Crm\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\Customer;

class ContactController extends Controller
{
    public function index(Customer $customer)
    {
        return ContactResource::collection($customer->contacts);
    }

    public function store(StoreContactRequest $request, Customer $customer)
    {
        $contact = $customer->contacts()->create($request->validated());

        return new ContactResource($contact);
    }

    public function update(UpdateContactRequest $request, Customer $customer, Contact $contact)
    {
        abort_unless($contact->customer_id === $customer->id, 404);

        $contact->update($request->validated());

        return new ContactResource($contact);
    }

    public function destroy(Customer $customer, Contact $contact)
    {
        abort_unless($contact->customer_id === $customer->id, 404);

        $contact->delete();

        return response()->json(status: 204);
    }
}
