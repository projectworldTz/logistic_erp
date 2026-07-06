<?php

namespace App\Services\ErrorLogging;

use App\Models\ErrorLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ErrorLogger
{
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'new_password_confirmation',
        'token',
        'secret',
        'api_key',
    ];

    public static function statusCodeFor(Throwable $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        // These well-known exception types carry their own status but don't
        // implement HttpExceptionInterface — Laravel's default handler
        // special-cases them, and we must recognize the same status here or
        // we'll wrongly intercept normal validation/auth/not-found responses.
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return $e->status;
        }

        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            return 401;
        }

        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return $e->status ?? 403;
        }

        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return 404;
        }

        return 500;
    }

    public static function log(Throwable $e, ?Request $request = null): ?ErrorLog
    {
        try {
            return ErrorLog::create([
                'reference' => self::generateReference(),
                'tenant_id' => app(TenantContext::class)->id(),
                'user_id' => $request?->user()?->id,
                'exception_class' => get_class($e),
                'message' => $e->getMessage() !== '' ? $e->getMessage() : '(no message)',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 8000),
                'method' => $request?->method(),
                'url' => $request?->fullUrl(),
                'status_code' => self::statusCodeFor($e),
                'request_payload' => $request ? self::sanitize($request->all()) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (Throwable) {
            // Logging must never throw a secondary exception out of the exception handler.
            return null;
        }
    }

    private static function generateReference(): string
    {
        do {
            $reference = strtoupper(bin2hex(random_bytes(4)));
        } while (ErrorLog::where('reference', $reference)->exists());

        return $reference;
    }

    private static function sanitize(array $data): array
    {
        foreach (self::REDACTED_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }
}
