<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->tenant_id) {
            abort(403, 'This account is not associated with a company.');
        }

        app(TenantContext::class)->set($user->tenant_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        return $next($request);
    }
}
