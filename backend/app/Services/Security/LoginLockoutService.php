<?php

namespace App\Services\Security;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;

class LoginLockoutService
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_MINUTES = 15;

    public function isLocked(User $user): bool
    {
        return $user->locked_until !== null && $user->locked_until->isFuture();
    }

    public function remainingLockoutSeconds(User $user): int
    {
        return $this->isLocked($user) ? max(0, (int) now()->diffInSeconds($user->locked_until, false)) : 0;
    }

    public function recordLockedAttempt(User $user, Request $request): void
    {
        $this->log($user, $user->email, $request, false, 'locked');
    }

    public function recordFailure(?User $user, string $email, Request $request, string $reason = 'invalid_credentials'): void
    {
        if ($user) {
            $user->failed_login_attempts++;

            if ($user->failed_login_attempts >= self::MAX_ATTEMPTS) {
                $user->locked_until = now()->addMinutes(self::LOCKOUT_MINUTES);
            }

            $user->saveQuietly();
        }

        $this->log($user, $email, $request, false, $reason);
    }

    public function recordSuccess(User $user, Request $request): void
    {
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->saveQuietly();

        $this->log($user, $user->email, $request, true, null);
    }

    private function log(?User $user, string $email, Request $request, bool $successful, ?string $reason): void
    {
        LoginAttempt::query()->create([
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'successful' => $successful,
            'reason' => $reason,
        ]);
    }
}
