<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    private const GENERIC_MESSAGE = 'If that email address is in our system, a password reset link has been sent.';

    /**
     * Send a password reset link, if the email exists — always responds the
     * same way regardless of outcome to avoid leaking account existence.
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => self::GENERIC_MESSAGE]);
    }

    public function reset(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->validated(),
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [$status === Password::INVALID_TOKEN
                    ? 'This reset link is invalid or has expired.'
                    : self::GENERIC_MESSAGE],
            ]);
        }

        return response()->json(['message' => 'Your password has been reset.']);
    }
}
