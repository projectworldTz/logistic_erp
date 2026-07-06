<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StorePortalMessageRequest;
use App\Http\Resources\CustomerMessageResource;
use App\Models\Customer;
use App\Models\CustomerMessage;
use Illuminate\Support\Facades\Auth;

class CustomerMessageController extends Controller
{
    public function index(Customer $customer)
    {
        CustomerMessage::query()
            ->where('customer_id', $customer->id)
            ->where('is_from_customer', true)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return CustomerMessageResource::collection(
            $customer->messages()->with('senderUser')->oldest()->get()
        );
    }

    public function store(StorePortalMessageRequest $request, Customer $customer)
    {
        $message = $customer->messages()->create([
            'sender_user_id' => Auth::id(),
            'is_from_customer' => false,
            'body' => $request->validated('body'),
        ]);

        return new CustomerMessageResource($message);
    }
}
