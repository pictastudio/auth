<?php

namespace PictaStudio\Auth;

use Illuminate\Auth\Notifications\{ResetPassword, VerifyEmail};
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use PictaStudio\Auth\Actions\GeneratePermissionsAction;
use PictaStudio\Auth\Console\Commands\{GeneratePermissionsCommand, InstallCommand};
use PictaStudio\Auth\Support\{Authorization, PermissionNameResolver};

class AuthServiceProvider extends ServiceProvider
{
    private static function buildUrlWithQuery(string $baseUrl, array $query): string
    {
        $parts = parse_url($baseUrl);

        if ($parts === false) {
            return $baseUrl;
        }

        $existingQuery = [];
        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $existingQuery);
        }

        $parts['query'] = http_build_query(array_merge($existingQuery, $query));

        return self::buildUrlFromParts($parts);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private static function buildUrlFromParts(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return "{$scheme}{$auth}{$host}{$port}{$path}{$query}{$fragment}";
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/picta-auth.php', 'picta-auth');

        $this->app->singleton(PermissionNameResolver::class);
        $this->app->singleton(Authorization::class);
        $this->app->singleton(GeneratePermissionsAction::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/picta-auth.php' => config_path('picta-auth.php'),
        ], 'auth-config');

        $this->publishes([
            __DIR__ . '/../bruno/auth' => base_path('bruno/auth'),
        ], 'auth-bruno');

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->configureNotificationFrontendUrls();

        if ($this->app->runningInConsole()) {
            $this->commands([
                GeneratePermissionsCommand::class,
                InstallCommand::class,
            ]);
        }
    }

    private function configureNotificationFrontendUrls(): void
    {
        $this->configureResetPasswordFrontendUrl();
        $this->configureEmailVerificationFrontendUrl();
    }

    private function configureResetPasswordFrontendUrl(): void
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            $frontendUrl = mb_trim((string) config('picta-auth.frontend_urls.reset_password', ''));

            if ($frontendUrl === '') {
                return $this->resolveResetPasswordUrl($notifiable, $token);
            }

            return self::buildUrlWithQuery($frontendUrl, [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });
    }

    private function resolveResetPasswordUrl($notifiable, string $token): string
    {
        /** @var Router $router */
        $router = $this->app->make('router');

        if ($router->has('password.reset')) {
            return url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
        }

        $defaultResetPath = mb_trim((string) config('picta-auth.routes.default_reset_password_path', '/reset-password'));
        $baseUrl = mb_rtrim((string) config('app.url', ''), '/');
        $path = '/' . mb_ltrim($defaultResetPath, '/');

        return self::buildUrlWithQuery($baseUrl . $path, [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    private function configureEmailVerificationFrontendUrl(): void
    {
        VerifyEmail::createUrlUsing(function ($notifiable): string {
            $verificationParameters = [
                'id' => (string) $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ];

            $verificationUrl = URL::temporarySignedRoute(
                'auth.verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                $verificationParameters
            );

            $frontendUrl = mb_trim((string) config('picta-auth.frontend_urls.email_verification', ''));

            if ($frontendUrl === '') {
                return $verificationUrl;
            }

            $query = $verificationParameters;
            $verificationQuery = parse_url($verificationUrl, PHP_URL_QUERY);

            if (is_string($verificationQuery) && $verificationQuery !== '') {
                $signedQuery = [];
                parse_str($verificationQuery, $signedQuery);
                $query = array_merge($query, $signedQuery);
            }

            return self::buildUrlWithQuery($frontendUrl, $query);
        });
    }
}
