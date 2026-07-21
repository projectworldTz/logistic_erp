<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Security\LoginLockoutService;
use App\Services\Security\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const CHALLENGE_CACHE_PREFIX = '2fa_challenge:';

    public function __construct(
        private readonly LoginLockoutService $lockoutService,
        private readonly TwoFactorService $twoFactorService,
    ) {}

    /**
     * Log in with email and password. Returns either a Sanctum token, or,
     * when the account has 2FA enabled, a short-lived challenge token that
     * must be exchanged via verifyTwoFactor().
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if ($user && $this->lockoutService->isLocked($user)) {
            $this->lockoutService->recordLockedAttempt($user, $request);

            throw ValidationException::withMessages([
                'email' => ["Too many failed attempts. Try again in {$this->lockoutService->remainingLockoutSeconds($user)} seconds."],
            ]);
        }

        if (! Auth::attempt($credentials)) {
            $this->lockoutService->recordFailure($user, $credentials['email'], $request);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->status !== UserStatus::Active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => ['This account is not active.'],
            ]);
        }

        if ($user->two_factor_enabled_at !== null) {
            Auth::logout();

            $challengeToken = Str::random(64);
            Cache::put(self::CHALLENGE_CACHE_PREFIX.$challengeToken, $user->id, now()->addMinutes(5));

            return response()->json([
                'requires_2fa' => true,
                'challenge_token' => $challengeToken,
            ]);
        }

        $this->lockoutService->recordSuccess($user, $request);
        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Exchange a 2FA challenge token plus a TOTP (or recovery) code for a
     * real Sanctum token, completing the login flow started in login().
     */
    public function verifyTwoFactor(TwoFactorChallengeRequest $request)
    {
        $data = $request->validated();
        $cacheKey = self::CHALLENGE_CACHE_PREFIX.$data['challenge_token'];
        $userId = Cache::get($cacheKey);

        if (! $userId) {
            throw ValidationException::withMessages([
                'challenge_token' => ['This login challenge has expired. Please log in again.'],
            ]);
        }

        /** @var User $user */
        $user = User::findOrFail($userId);

        if ($this->lockoutService->isLocked($user)) {
            throw ValidationException::withMessages([
                'code' => ["Too many failed attempts. Try again in {$this->lockoutService->remainingLockoutSeconds($user)} seconds."],
            ]);
        }

        if (! $this->passesTwoFactorCheck($user, $data['code'])) {
            $this->lockoutService->recordFailure($user, $user->email, $request, '2fa_failed');

            throw ValidationException::withMessages([
                'code' => ['The provided code is incorrect.'],
            ]);
        }

        Cache::forget($cacheKey);
        $this->lockoutService->recordSuccess($user, $request);
        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    private function passesTwoFactorCheck(User $user, string $code): bool
    {
        if ($this->twoFactorService->verify($user->two_factor_secret, $code)) {
            return true;
        }

        $recoveryCodes = $user->two_factor_recovery_codes ?? [];
        $normalized = Str::upper(trim($code));

        if (in_array($normalized, $recoveryCodes, true)) {
            $user->forceFill([
                'two_factor_recovery_codes' => array_values(array_diff($recoveryCodes, [$normalized])),
            ])->save();

            return true;
        }

        return false;
    }

    /**
     * Revoke the current access token.
     */
    public function logout()
    {
        $user = request()->user();
        $user?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Return the currently authenticated user.
     */
    public function me()
    {
        return new UserResource(request()->user());
    }

    /**
     * Self-service password change — available to every authenticated
     * account (staff and customer portal users alike, since both are the
     * same User model behind Sanctum), unlike the forgot-password flow
     * which only works from a logged-out state via an emailed token.
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($request->validated('password'))])->save();

        return response()->json(['message' => 'Password changed.']);
    }
}
