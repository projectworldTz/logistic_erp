<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;

class SubscriptionController extends Controller
{
    public function index()
    {
        return SubscriptionResource::collection(
            Subscription::query()->with(['plan', 'tenant'])->latest()->paginate(20)
        );
    }
}
