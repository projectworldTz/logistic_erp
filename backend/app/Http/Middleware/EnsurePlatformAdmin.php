<?php

namespace App\Http\Middleware;

use App\Support\Rbac\PermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_super_admin) {
            abort(403, 'This action requires platform administrator access.');
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId(PermissionRegistry::PLATFORM_TEAM_ID);

        return $next($request);
    }
}
