<?php

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use PictaStudio\Auth\AuthServiceProvider;

it('does not read legacy library wrapped config values', function (): void {
    config()->set('picta-auth', [
        'library' => [
            'routes' => [
                'prefix' => 'legacy-auth',
            ],
            'frontend_urls' => [
                'reset_password' => 'https://frontend.example/reset-password',
            ],
        ],
    ]);

    (new AuthServiceProvider(app()))->register();

    expect(config('picta-auth.library'))->toBeArray()
        ->and(config('picta-auth.routes.prefix'))->toBe('api/auth')
        ->and(config('sanctum.prefix'))->toBe('api/auth')
        ->and(config('picta-auth.frontend_urls.reset_password'))->toBeNull();
});

it('keeps package defaults when only part of a nested config is overridden', function (): void {
    config()->set('picta-auth', [
        'routes' => [
            'prefix' => 'custom-auth',
        ],
    ]);

    (new AuthServiceProvider(app()))->register();

    expect(config('picta-auth.routes.stateful_middleware'))->toContain(EnsureFrontendRequestsAreStateful::class)
        ->and(config('picta-auth.routes.prefix'))->toBe('custom-auth')
        ->and(config('sanctum.prefix'))->toBe('custom-auth')
        ->and(config('picta-auth.routes.auth_middleware'))->toBe(['auth:sanctum'])
        ->and(config('picta-auth.routes.verification_middleware'))->toBe(['auth:sanctum', 'signed', 'throttle:6,1']);
});
