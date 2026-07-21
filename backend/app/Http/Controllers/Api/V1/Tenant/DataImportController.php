<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Imports\DataImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DataImportController extends Controller
{
    /**
     * module => permission required to bulk-import it. Reuses each module's
     * existing "manage" permission rather than introducing new RBAC entries.
     */
    private const MODULE_PERMISSIONS = [
        'customers' => 'crm.customers.manage',
        'leads' => 'crm.leads.manage',
        'attendance' => 'hr.attendance.manage',
    ];

    public function import(Request $request, string $module, DataImportService $service)
    {
        abort_unless(array_key_exists($module, self::MODULE_PERMISSIONS), 404);
        abort_unless(Auth::user()->can(self::MODULE_PERMISSIONS[$module]), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        $result = $service->import($module, $request->file('file')->getRealPath());

        return response()->json($result);
    }
}
