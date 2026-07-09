<?php

use Illuminate\Support\Facades\Route;
use PictaStudio\Auth\Http\Controllers\{AuthenticatedUserController, EmailVerificationNotificationController, ForgotPasswordController, LoginController, LogoutController, RegisterController, ResetPasswordController, UserController, VerifyEmailController};

$routeMiddleware = array_values(array_unique(array_merge(
    (array) config('picta-auth.routes.middleware', ['api']),
    (array) config('picta-auth.routes.stateful_middleware', [])
)));

$userRouteMiddleware = array_values(array_unique(array_merge(
    (array) config('picta-auth.routes.middleware', ['api']),
    (array) config('picta-auth.routes.stateful_middleware', []),
    (array) config('picta-auth.routes.auth_middleware', ['auth:sanctum'])
)));

Route::prefix(config('picta-auth.routes.prefix', 'auth'))
    ->middleware($routeMiddleware)
    ->group(function (): void {
        Route::post('/register', RegisterController::class)->name('auth.register');
        Route::post('/login', LoginController::class)->name('auth.login');
        Route::post('/forgot-password', ForgotPasswordController::class)->name('auth.password.email');
        Route::post('/reset-password', ResetPasswordController::class)->name('auth.password.reset');

        Route::middleware((array) config('picta-auth.routes.auth_middleware', ['auth:sanctum']))
            ->group(function (): void {
                Route::get('/me', AuthenticatedUserController::class)->name('auth.me');
                Route::post('/logout', LogoutController::class)->name('auth.logout');
                Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
                    ->name('auth.verification.send');
            });

        Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware((array) config('picta-auth.routes.verification_middleware', ['auth:sanctum', 'signed', 'throttle:6,1']))
            ->name('auth.verification.verify');
    });

Route::prefix(config('picta-auth.users.routes.prefix', 'api/users'))
    ->middleware($userRouteMiddleware)
    ->group(function (): void {
        Route::get('/', [UserController::class, 'index'])->name('users.index');
        Route::post('/', [UserController::class, 'store'])->name('users.store');
        Route::get('/{user}', [UserController::class, 'show'])->name('users.show');
        Route::match(['put', 'patch'], '/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
