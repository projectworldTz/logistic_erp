<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    /**
     * Platform-wide audit trail (unscoped — no tenant bound on this route).
     */
    public function index()
    {
        return AuditLogResource::collection(
            AuditLog::query()->with('user')->latest('created_at')->paginate(30)
        );
    }
}
