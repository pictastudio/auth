<?php

return [
    'library' => [
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
            'prefix' => 'auth',
            'middleware' => ['api'],
            'auth_middleware' => ['api', 'auth:sanctum'],
            'verification_middleware' => ['api', 'auth:sanctum', 'signed', 'throttle:6,1'],
        ],

        'password_broker' => env('AUTH_LIBRARY_PASSWORD_BROKER', 'users'),

        'sanctum' => [
            'token_name' => env('AUTH_LIBRARY_TOKEN_NAME', 'auth-token'),
            'abilities' => ['*'],
        ],
    ],
];
