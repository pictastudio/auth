<?php

use PictaStudio\Auth\Support\Authorization;

if (! function_exists('auth_authorize')) {
    function auth_authorize(string|object|null $model = null, ?string $action = null, mixed $user = null): bool
    {
        if ($model === null || ! is_string($action) || $action === '') {
            return false;
        }

        $authenticatedUser = $user ?? auth()->guard()->user();

        return app(Authorization::class)->allows($authenticatedUser, $model, $action);
    }
}
