<?php

use Illuminate\Support\Facades\Route;
use PictaStudio\Auth\Http\Controllers\{AuthenticatedUserController, EmailVerificationNotificationController, ForgotPasswordController, LoginController, LogoutController, ResetPasswordController, VerifyEmailController};

Route::prefix(config('picta-auth.routes.prefix', 'auth'))
    ->middleware(config('picta-auth.routes.middleware', ['api']))
    ->group(function (): void {
        Route::post('/login', LoginController::class)->name('auth.login');
        Route::post('/forgot-password', ForgotPasswordController::class)->name('auth.password.email');
        Route::post('/reset-password', ResetPasswordController::class)->name('auth.password.reset');

        Route::middleware(config('picta-auth.routes.auth_middleware', ['api', 'auth:sanctum']))
            ->group(function (): void {
                Route::get('/me', AuthenticatedUserController::class)->name('auth.me');
                Route::post('/logout', LogoutController::class)->name('auth.logout');
                Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
                    ->name('auth.verification.send');
            });

        Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(config('picta-auth.routes.verification_middleware', ['api', 'auth:sanctum', 'signed', 'throttle:6,1']))
            ->name('auth.verification.verify');
    });
