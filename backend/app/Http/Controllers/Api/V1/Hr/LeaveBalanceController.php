<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveBalanceResource;
use App\Models\LeaveBalance;
use Illuminate\Http\Request;

class LeaveBalanceController extends Controller
{
    public function index(Request $request)
    {
        return LeaveBalanceResource::collection(
            LeaveBalance::query()
                ->with(['employee', 'leaveType'])
                ->when($request->query('employee_id'), fn ($query, $id) => $query->where('employee_id', $id))
                ->when($request->query('year'), fn ($query, $year) => $query->where('year', $year), fn ($query) => $query->where('year', now()->year))
                ->get()
        );
    }
}
