<?php

namespace PictaStudio\Auth\Http\Controllers;

use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Auth;

class LogoutController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $currentAccessToken = $user->currentAccessToken();

        if ($currentAccessToken !== null && method_exists($currentAccessToken, 'delete')) {
            $currentAccessToken->delete();
        }

        $guard = config('picta-auth.guard', config('auth.defaults.guard', 'web'));
        $authGuard = Auth::guard($guard);

        if (method_exists($authGuard, 'logout')) {
            $authGuard->logout();
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out.']);
    }
}
