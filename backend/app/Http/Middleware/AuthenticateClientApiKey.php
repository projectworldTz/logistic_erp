<?php

namespace App\Http\Middleware;

use App\Models\CustomerApiKey;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates external Client API requests via a plaintext key (never a
 * Sanctum session token) supplied as `Authorization: Bearer <key>` or an
 * `X-Api-Key` header. The matched key carries its own tenant_id/customer_id,
 * which is how TenantContext gets set here — there is no logged-in user to
 * read it from, unlike every other authenticated route in this app.
 */
class AuthenticateClientApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = $request->bearerToken() ?: $request->header('X-Api-Key');

        if (! $plaintext) {
            abort(401, 'Missing API key. Pass it as "Authorization: Bearer <key>" or an "X-Api-Key" header.');
        }

        $apiKey = CustomerApiKey::query()
            ->whereNull('revoked_at')
            ->where('key_hash', hash('sha256', $plaintext))
            ->first();

        if (! $apiKey) {
            abort(401, 'Invalid or revoked API key.');
        }

        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();

        app(TenantContext::class)->set($apiKey->tenant_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($apiKey->tenant_id);
        $request->attributes->set('client_api_key', $apiKey);

        return $next($request);
    }
}
