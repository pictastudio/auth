<?php

namespace PictaStudio\Auth\Http\Controllers;

use Illuminate\Http\{JsonResponse, Request};

class EmailVerificationNotificationController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (method_exists($user, 'hasVerifiedEmail') && $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        if (!method_exists($user, 'sendEmailVerificationNotification')) {
            return response()->json(['message' => 'Email verification is not available for this model.'], 500);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.']);
    }
}
