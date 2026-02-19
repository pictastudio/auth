<?php

namespace PictaStudio\Auth\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordController
{
    public function __invoke(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::broker(config('auth.library.password_broker', 'users'))
            ->reset($credentials, function ($user) use ($credentials): void {
                $user->forceFill([
                    'password' => Hash::make($credentials['password']),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            });

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }
}
