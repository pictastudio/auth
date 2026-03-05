<?php

namespace PictaStudio\Auth\Http\Controllers;

use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Hash};
use Illuminate\Validation\Rule;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class RegisterController
{
    public function __invoke(Request $request): JsonResponse
    {
        $guard = config('picta-auth.guard', config('auth.defaults.guard', 'web'));
        $provider = config("auth.guards.{$guard}.provider");
        $model = is_string($provider) ? config("auth.providers.{$provider}.model") : null;

        if (!is_string($model) || !class_exists($model)) {
            return response()->json([
                'message' => 'Unable to resolve the auth model for the configured guard.',
            ], 500);
        }

        /** @var \Illuminate\Database\Eloquent\Model $authModel */
        $authModel = new $model;

        $credentials = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email', Rule::unique($authModel->getTable(), 'email')],
            'password' => config('picta-auth.password_rules', ['required', 'string', 'confirmed', 'min:8']),
            'token_name' => ['sometimes', 'string'],
            'issue_token' => ['sometimes', 'boolean'],
        ]);

        $configuredIssueToken = config('picta-auth.sanctum.issue_token_by_default');
        $defaultIssueToken = is_bool($configuredIssueToken)
            ? $configuredIssueToken
            : !EnsureFrontendRequestsAreStateful::fromFrontend($request);
        $issueToken = $request->has('issue_token')
            ? $request->boolean('issue_token')
            : $defaultIssueToken;

        if ($issueToken && !method_exists($model, 'createToken')) {
            return response()->json([
                'message' => 'The auth model must use Laravel Sanctum HasApiTokens.',
            ], 500);
        }

        $authGuard = Auth::guard($guard);

        if (!$issueToken && !method_exists($authGuard, 'login')) {
            return response()->json([
                'message' => 'The configured guard does not support session authentication.',
            ], 500);
        }

        /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
        $user = new $model;
        $user->forceFill([
            'name' => $credentials['name'],
            'email' => $credentials['email'],
            'password' => Hash::make($credentials['password']),
        ])->save();

        if (method_exists($user, 'sendEmailVerificationNotification')
            && (!method_exists($user, 'hasVerifiedEmail') || !$user->hasVerifiedEmail())) {
            $user->sendEmailVerificationNotification();
        }

        if (!$issueToken) {
            $authGuard->login($user);

            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            return response()->json([
                'authenticated_via' => 'cookie',
                'user' => $user,
            ], 201);
        }

        $tokenName = $credentials['token_name'] ?? config('picta-auth.sanctum.token_name', 'auth-token');
        $abilities = config('picta-auth.sanctum.abilities', ['*']);

        $token = $user->createToken($tokenName, is_array($abilities) ? $abilities : ['*']);

        return response()->json([
            'authenticated_via' => 'token',
            'token_type' => 'Bearer',
            'token' => $token->plainTextToken,
            'user' => $user,
        ], 201);
    }
}
