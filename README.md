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

## Configuration

Permissions are generated from `config/auth.php` under `auth.library.permissions`.

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
- Default guard resolution from `auth.library.guard`
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

## Testing

```bash
composer test
```
