<?php

namespace PictaStudio\Auth;

use Illuminate\Support\ServiceProvider;
use PictaStudio\Auth\Actions\GeneratePermissionsAction;
use PictaStudio\Auth\Console\GeneratePermissionsCommand;
use PictaStudio\Auth\Support\{Authorization, PermissionNameResolver};

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/auth.php', 'auth');

        $this->app->singleton(PermissionNameResolver::class);
        $this->app->singleton(Authorization::class);
        $this->app->singleton(GeneratePermissionsAction::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/auth.php' => config_path('auth.php'),
        ], 'auth-config');

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GeneratePermissionsCommand::class,
            ]);
        }
    }
}
