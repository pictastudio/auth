<?php

namespace PictaStudio\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->currentAccessToken() !== null) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }
}
