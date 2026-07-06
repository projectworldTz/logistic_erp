<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StorePortalMessageRequest;
use App\Http\Resources\CustomerMessageResource;
use App\Models\CustomerMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalMessageController extends Controller
{
    public function index(Request $request)
    {
        $customerId = $request->user()->customer_id;

        CustomerMessage::query()
            ->where('customer_id', $customerId)
            ->where('is_from_customer', false)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return CustomerMessageResource::collection(
            CustomerMessage::query()
                ->where('customer_id', $customerId)
                ->oldest()
                ->get()
        );
    }

    public function store(StorePortalMessageRequest $request)
    {
        $message = CustomerMessage::query()->create([
            'customer_id' => $request->user()->customer_id,
            'sender_user_id' => Auth::id(),
            'is_from_customer' => true,
            'body' => $request->validated('body'),
        ]);

        return new CustomerMessageResource($message);
    }
}
