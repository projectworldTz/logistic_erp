<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DisableTwoFactorRequest;
use App\Http\Requests\Auth\EnableTwoFactorRequest;
use App\Models\User;
use App\Services\Security\TwoFactorService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactorService) {}

    /**
     * Generate a new (not-yet-persisted) TOTP secret plus its provisioning
     * QR code. The frontend must resubmit this same secret to enable().
     */
    public function setup()
    {
        /** @var User $user */
        $user = request()->user();

        $secret = $this->twoFactorService->generateSecret();
        $uri = $this->twoFactorService->provisioningUri($user, $secret);

        return response()->json([
            'secret' => $secret,
            'qr_svg' => $this->twoFactorService->qrCodeSvg($uri),
        ]);
    }

    /**
     * Confirm possession of the authenticator by verifying a code against
     * the pending secret, then persist it and issue one-time recovery codes.
     */
    public function enable(EnableTwoFactorRequest $request)
    {
        $data = $request->validated();

        if (! $this->twoFactorService->verify($data['secret'], $data['code'])) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = $request->user();
        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $data['secret'],
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_enabled_at' => now(),
        ])->save();

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    public function disable(DisableTwoFactorRequest $request)
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($request->validated()['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled_at' => null,
        ])->save();

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }
}
