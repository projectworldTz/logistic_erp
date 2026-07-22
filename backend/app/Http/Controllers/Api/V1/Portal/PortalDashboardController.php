<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CustomerMessage;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\UserNotification;
use App\Support\Currency\CurrencyConverter;
use Illuminate\Http\Request;

class PortalDashboardController extends Controller
{
    public function summary(Request $request)
    {
        $customerId = $request->user()->customer_id;
        $company = Company::query()->firstOrFail();

        return response()->json([
            'active_shipments' => Shipment::query()
                ->where('customer_id', $customerId)
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->count(),
            'outstanding_balance' => round(Invoice::query()
                ->where('customer_id', $customerId)
                ->whereIn('status', ['sent', 'overdue'])
                ->get(['total_amount', 'currency'])
                ->sum(fn ($invoice) => CurrencyConverter::toSystemCurrency((float) $invoice->total_amount, $invoice->currency, $company)), 2),
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
