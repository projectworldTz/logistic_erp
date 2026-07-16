<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Backup\TenantBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BackupController extends Controller
{
    public function export(TenantBackupService $service)
    {
        $tenantId = Auth::user()->tenant_id;
        $backup = $service->export($tenantId);

        $filename = 'backup-'.now()->format('Y-m-d-His').'.json';

        return response()->streamDownload(
            fn () => print(json_encode($backup, JSON_PRETTY_PRINT)),
            $filename,
            ['Content-Type' => 'application/json']
        );
    }

    public function restore(Request $request, TenantBackupService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $contents = file_get_contents($request->file('file')->getRealPath());
        $backup = json_decode($contents, true);

        if (! is_array($backup)) {
            throw ValidationException::withMessages(['file' => 'This file is not valid JSON.']);
        }

        try {
            $service->restore(Auth::user()->tenant_id, $backup);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Restore complete.']);
    }
}
