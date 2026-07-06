<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Log in with email and password, returning a Sanctum token.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->status !== \App\Enums\UserStatus::Active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => ['This account is not active.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
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
}
