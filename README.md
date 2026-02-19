# pictastudio/auth

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pictastudio/auth.svg?style=flat-square)](https://packagist.org/packages/pictastudio/auth)
[![Total Downloads](https://img.shields.io/packagist/dt/pictastudio/auth.svg?style=flat-square)](https://packagist.org/packages/pictastudio/auth)

Opinionated API authentication and authorization for Laravel using Sanctum and Spatie roles/permissions.

## Features

- Common API auth routes: login, logout, current user, forgot/reset password, email verification.
- Config-driven permission generation with pattern `{model}:{action}`.
- Config-driven role bootstrap with defaults:
  - `root` (all permissions)
  - `admin` (all permissions)
  - `user` (no permissions assigned by default)
- Global helper `auth_authorize(...)` to run authorization checks from anywhere.
- User model trait to bootstrap Sanctum + Spatie integration quickly.

## Installation

```bash
composer require pictastudio/auth
```

Publish config:

```bash
php artisan vendor:publish --tag=auth-config
```

Publish Sanctum personal access tokens migration:

```bash
php artisan vendor:publish --tag=auth-migrations
```

Publish Bruno API collection:

```bash
php artisan vendor:publish --tag=auth-bruno
```

## Configuration

Permissions are generated from `config/picta-auth.php` under `picta-auth.permissions`.

```php
return [
    'library' => [
        'permissions' => [
            'models' => [
                'post' => \App\Models\Post::class,
                \App\Models\Comment::class,
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
        ],
    ],
];
```

For API-only projects, you can also point notification links to frontend routes:

```php
return [
    'library' => [
        'frontend_urls' => [
            'reset_password' => env('AUTH_LIBRARY_FRONTEND_RESET_PASSWORD_URL'),
            'email_verification' => env('AUTH_LIBRARY_FRONTEND_EMAIL_VERIFICATION_URL'),
        ],
    ],
];
```

- `AUTH_LIBRARY_FRONTEND_RESET_PASSWORD_URL`: frontend page that receives `token` and `email` query params.
- `AUTH_LIBRARY_FRONTEND_EMAIL_VERIFICATION_URL`: frontend page that receives signed verification query params (`id`, `hash`, `expires`, `signature`).
- If `AUTH_LIBRARY_FRONTEND_RESET_PASSWORD_URL` is not set and no `password.reset` route exists, the package falls back to `APP_URL` + `picta-auth.routes.default_reset_password_path` (default: `/reset-password`).

Password reset validation rules are configurable via `picta-auth.password_rules`:

```php
return [
    'library' => [
        'password_rules' => ['required', 'string', 'confirmed', 'min:12'],
    ],
];
```

If you publish the Bruno collection, create your local env file from the template:

```bash
cp bruno/auth/environments/Local.example.bru bruno/auth/environments/Local.bru
```

`bruno/auth/environments/Local.bru` is gitignored so personal values are not tracked.

Generated permission names follow:

```text
{model}:{action}
```

## Generate Permissions and Roles

```bash
php artisan auth:permissions:generate
```

This command keeps existing records and only creates missing permissions/roles.

## User Model Trait

Use the package trait on your User model to get:

- Sanctum API tokens
- Spatie roles/permissions support
- Default guard resolution from `picta-auth.guard`
- Convenience method: `$user->canAuthorize($model, $action)`

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use PictaStudio\Auth\Concerns\HasAuthFeatures;

class User extends Authenticatable
{
    use HasAuthFeatures;
}
```

## Global Helper

```php
auth_authorize(\App\Models\Post::class, 'view', $user);
auth_authorize(\App\Models\Post::class, 'update'); // defaults to auth()->guard()->user()
```

## API Routes

Mounted under `/auth` by default:

- `POST /auth/login`
- `GET /auth/me`
- `POST /auth/logout`
- `POST /auth/forgot-password`
- `POST /auth/reset-password`
- `POST /auth/email/verification-notification`
- `GET /auth/verify-email/{id}/{hash}`

## Morph Map

Inside your AppServiceProvider add this to ensure the relation morph map is registered:

```php
use Illuminate\Support\ServiceProvider;
use PictaStudio\Auth\AuthServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Relation::morphMap([
            'user' => User::class,
        ]);
    }
}
```

## Testing

```bash
composer test
```
