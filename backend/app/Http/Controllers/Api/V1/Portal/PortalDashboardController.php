<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomerMessage;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class PortalDashboardController extends Controller
{
    public function summary(Request $request)
    {
        $customerId = $request->user()->customer_id;

        return response()->json([
            'active_shipments' => Shipment::query()
                ->where('customer_id', $customerId)
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->count(),
            'outstanding_balance' => (float) Invoice::query()
                ->where('customer_id', $customerId)
                ->whereIn('status', ['sent', 'overdue'])
                ->sum('total_amount'),
            'unread_messages' => CustomerMessage::query()
                ->where('customer_id', $customerId)
                ->where('is_from_customer', false)
                ->whereNull('read_at')
                ->count(),
            'unread_notifications' => UserNotification::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }
}
