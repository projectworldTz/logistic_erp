<?php

use App\Services\ErrorLogging\ErrorLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => \App\Http\Middleware\ResolveTenant::class,
            'platform.admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
            'portal' => \App\Http\Middleware\EnsurePortalUser::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        // SubstituteBindings (implicit route-model binding, e.g. `Shipment $shipment`)
        // is part of Laravel's default global middleware stack, so it runs before
        // any route-specific middleware unless explicitly prioritized — including
        // 'tenant', which is what sets TenantContext for the TenantScope global
        // scope. Without this, {shipment}/{clearingFile}/etc. route parameters
        // resolve with NO tenant filter at all, letting one tenant fetch another
        // tenant's record by ID on every show/update/destroy endpoint.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\ResolveTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $statusCode = ErrorLogger::statusCodeFor($e);

            if ($statusCode < 500) {
                return null;
            }

            $errorLog = ErrorLogger::log($e, $request);

            return response()->json([
                'message' => 'Something went wrong on our end. Please contact support with this reference code.',
                'reference' => $errorLog?->reference ?? 'UNLOGGED',
            ], $statusCode);
        });
    })->create();
