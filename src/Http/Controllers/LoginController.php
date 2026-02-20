<?php

namespace PictaStudio\Auth\Http\Controllers;

use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Hash};
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class LoginController
{
    public function __invoke(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'token_name' => ['sometimes', 'string'],
            'issue_token' => ['sometimes', 'boolean'],
        ]);

        $guard = config('picta-auth.guard', config('auth.defaults.guard', 'web'));
        $provider = config("auth.guards.{$guard}.provider");
        $model = is_string($provider) ? config("auth.providers.{$provider}.model") : null;

        if (!is_string($model) || !class_exists($model)) {
            return response()->json([
                'message' => 'Unable to resolve the auth model for the configured guard.',
            ], 500);
        }

        /** @var \Illuminate\Contracts\Auth\Authenticatable|null $user */
        $user = $model::query()->where('email', $credentials['email'])->first();

        if ($user === null || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are invalid.',
            ], 422);
        }

        $configuredIssueToken = config('picta-auth.sanctum.issue_token_by_default');
        $defaultIssueToken = is_bool($configuredIssueToken)
            ? $configuredIssueToken
            : !EnsureFrontendRequestsAreStateful::fromFrontend($request);
        $issueToken = $request->has('issue_token')
            ? $request->boolean('issue_token')
            : $defaultIssueToken;

        if (!$issueToken) {
            $authGuard = Auth::guard($guard);

            if (!method_exists($authGuard, 'login')) {
                return response()->json([
                    'message' => 'The configured guard does not support session authentication.',
                ], 500);
            }

            $authGuard->login($user);

            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            return response()->json([
                'authenticated_via' => 'cookie',
                'user' => $user,
            ]);
        }

        if (!method_exists($user, 'createToken')) {
            return response()->json([
                'message' => 'The auth model must use Laravel Sanctum HasApiTokens.',
            ], 500);
        }

        $tokenName = $credentials['token_name'] ?? config('picta-auth.sanctum.token_name', 'auth-token');
        $abilities = config('picta-auth.sanctum.abilities', ['*']);

        $token = $user->createToken($tokenName, is_array($abilities) ? $abilities : ['*']);

        return response()->json([
            'authenticated_via' => 'token',
            'token_type' => 'Bearer',
            'token' => $token->plainTextToken,
            'user' => $user,
        ]);
    }
}
