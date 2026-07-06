<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorLogResource;
use App\Models\ErrorLog;
use Illuminate\Http\Request;

class ErrorLogController extends Controller
{
    /**
     * Platform-wide error log (unscoped — no tenant bound on this route).
     */
    public function index(Request $request)
    {
        $query = ErrorLog::query()->with(['tenant', 'user'])->latest('created_at');

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->integer('tenant_id'));
        }

        if ($request->has('resolved')) {
            $request->boolean('resolved')
                ? $query->whereNotNull('resolved_at')
                : $query->whereNull('resolved_at');
        }

        if ($request->filled('q')) {
            $search = $request->string('q');
            $query->where(fn ($q) => $q
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")
                ->orWhere('exception_class', 'like', "%{$search}%"));
        }

        return ErrorLogResource::collection($query->paginate(30));
    }

    public function show(ErrorLog $errorLog)
    {
        return new ErrorLogResource($errorLog->load(['tenant', 'user']));
    }

    public function resolve(ErrorLog $errorLog)
    {
        $errorLog->update(['resolved_at' => now()]);

        return new ErrorLogResource($errorLog->load(['tenant', 'user']));
    }
}
