<?php

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return [
    'guard' => env('AUTH_LIBRARY_GUARD', env('AUTH_GUARD', 'web')),

    'permissions' => [
        'models' => [
            // \App\Models\User::class,
            // 'post' => \App\Models\Post::class,
        ],

        'actions' => [
            'view-any',
            'view',
            'create',
            'show',
            'update',
            'delete',
            'force-delete',
            'restore',
        ],

        'delimiter' => ':',
    ],

    'roles' => [
        'root' => [
            'all_permissions' => true,
        ],
        'admin' => [
            'all_permissions' => true,
        ],
        'user' => [
            'all_permissions' => false,
        ],
    ],

    'routes' => [
        'prefix' => 'api/auth',
        'middleware' => ['api'],
        'stateful_middleware' => [EnsureFrontendRequestsAreStateful::class],
        'auth_middleware' => ['auth:sanctum'],
        'verification_middleware' => ['auth:sanctum', 'signed', 'throttle:6,1'],
        'default_reset_password_path' => '/reset-password',
    ],

    'users' => [
        'routes' => [
            'prefix' => 'api/users',
        ],

        'pagination' => [
            'per_page' => 15,
            'max_per_page' => 100,
        ],
    ],

    'password_broker' => env('AUTH_LIBRARY_PASSWORD_BROKER', 'users'),

    'password_rules' => [
        'required',
        'string',
        'confirmed',
        'min:8',
    ],

    'frontend_urls' => [
        // Example: https://app.example.com/reset-password
        'reset_password' => env('AUTH_LIBRARY_FRONTEND_RESET_PASSWORD_URL'),
        // Example: https://app.example.com/verify-email
        'email_verification' => env('AUTH_LIBRARY_FRONTEND_EMAIL_VERIFICATION_URL'),
    ],

    'sanctum' => [
        'token_name' => env('AUTH_LIBRARY_TOKEN_NAME', 'auth-token'),
        'abilities' => ['*'],
        'issue_token_by_default' => env('AUTH_LIBRARY_ISSUE_TOKEN_BY_DEFAULT'),
    ],
];
