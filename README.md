# pictastudio/auth

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pictastudio/auth.svg?style=flat-square)](https://packagist.org/packages/pictastudio/auth)
[![Total Downloads](https://img.shields.io/packagist/dt/pictastudio/auth.svg?style=flat-square)](https://packagist.org/packages/pictastudio/auth)

Opinionated API authentication and authorization for Laravel using Sanctum and Spatie roles/permissions.

## Features

- Common API auth routes: register, login, logout, current user, forgot/reset password, email verification.
- Dual-mode Sanctum authentication:
  - Stateful cookie/session auth for first-party frontend requests.
  - Bearer token auth for third-party API consumers.
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

### Install Auth

```bash
php artisan auth:install
```

## Configuration

Permissions are generated from `config/picta-auth.php` under `picta-auth.permissions`.

```php
return [
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
];
```

For API-only projects, you can also point notification links to frontend routes:

```php
return [
    'frontend_urls' => [
        'reset_password' => env('AUTH_LIBRARY_FRONTEND_RESET_PASSWORD_URL'),
        'email_verification' => env('AUTH_LIBRARY_FRONTEND_EMAIL_VERIFICATION_URL'),
    ],
];
```

- `AUTH_LIBRARY_FRONTEND_RESET_PASSWORD_URL`: frontend page that receives `token` and `email` query params.
- `AUTH_LIBRARY_FRONTEND_EMAIL_VERIFICATION_URL`: frontend page that receives signed verification query params (`id`, `hash`, `expires`, `signature`).
- If `AUTH_LIBRARY_FRONTEND_RESET_PASSWORD_URL` is not set and no `password.reset` route exists, the package falls back to `APP_URL` + `picta-auth.routes.default_reset_password_path` (default: `/reset-password`).

Password reset validation rules are configurable via `picta-auth.password_rules`:

```php
return [
    'password_rules' => ['required', 'string', 'confirmed', 'min:12'],
];
```

- `AUTH_LIBRARY_ISSUE_TOKEN_BY_DEFAULT` (optional): force login responses to always issue (`true`) or not issue (`false`) bearer tokens. By default, the package auto-detects: stateful frontend requests get cookie auth, non-stateful requests get bearer tokens.

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

Mounted under `/api/auth` by default:

- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/auth/me`
- `POST /api/auth/logout`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `POST /api/auth/email/verification-notification`
- `GET /api/auth/verify-email/{id}/{hash}`

## Registration

`POST /api/auth/register` accepts:

- `name` (`string`, required)
- `email` (`email`, required, unique)
- `password` (`string`, required, defaults to `picta-auth.password_rules`)
- `password_confirmation` (required when using the default `confirmed` password rule)

Optional payload fields:

- `issue_token` (`boolean`): force token issuance on/off.
- `token_name` (`string`): token name when issuing bearer tokens.

Like login, registration defaults to cookie auth for stateful frontend requests and bearer token auth for non-stateful requests.

## Login Modes

`POST /api/auth/login` supports both Sanctum modes:

- First-party frontend (stateful request, `Origin`/`Referer` in `sanctum.stateful`): authenticates with cookies/session by default.
- Third-party clients (non-stateful request): returns a bearer token by default.

Optional payload fields:

- `issue_token` (`boolean`): force token issuance on/off.
- `token_name` (`string`): token name when issuing bearer tokens.

To use cookie-based SPA auth, make sure your frontend domain is in `config/sanctum.php` (`sanctum.stateful`) and keep `picta-auth.routes.stateful_middleware` enabled.

## Frontend Integration (Cookie Auth)

Use this flow when your first-party frontend authenticates with cookies (Sanctum stateful mode) instead of bearer tokens.

### 1. Backend setup

Set your frontend as a stateful domain and allow cross-site credentials.

Example `.env`:

```env
APP_URL=http://api.test
SESSION_DRIVER=cookie
SESSION_DOMAIN=.test
SANCTUM_STATEFUL_DOMAINS=app.test,localhost:3000,127.0.0.1:3000
```

Example `config/cors.php`:

```php
return [
    'paths' => ['api/*', 'api/auth/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://app.test', 'http://localhost:3000'],
    'allowed_headers' => ['*'],
    'supports_credentials' => true,
];
```

Notes:

- Keep `picta-auth.routes.stateful_middleware` enabled (default).
- The Sanctum CSRF route is exposed under the same prefix as your auth routes (`GET /api/auth/csrf-cookie` by default).
- The package will default `POST /api/auth/login` to cookie auth for these stateful frontend requests.
- For localhost-only development, you can leave `SESSION_DOMAIN` unset.
- For production subdomains, use a shared session domain (for example `.example.com`).

### 2. Frontend request flow

1. Get CSRF cookie: `GET /api/auth/csrf-cookie` with credentials.
2. Login: `POST /api/auth/login` with email/password and credentials.
3. Read current user: `GET /api/auth/me` with credentials.
4. Logout: `POST /api/auth/logout` with credentials.

### 3. Frontend example (Axios)

```ts
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://api.test',
  withCredentials: true,
  withXSRFToken: true,
});

export async function login(email: string, password: string) {
  await api.get('/api/auth/csrf-cookie');

  await api.post('/api/auth/login', { email, password });

  const { data } = await api.get('/api/auth/me');
  return data.user;
}

export async function logout() {
  await api.post('/api/auth/logout');
}
```

### 4. Frontend example (Fetch)

```ts
const API_BASE = 'http://api.test';

export async function login(email: string, password: string) {
  await fetch(`${API_BASE}/api/auth/csrf-cookie`, {
    method: 'GET',
    credentials: 'include',
  });

  await fetch(`${API_BASE}/api/auth/login`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });

  const meResponse = await fetch(`${API_BASE}/api/auth/me`, {
    method: 'GET',
    credentials: 'include',
  });

  const me = await meResponse.json();
  return me.user;
}
```

### 5. If login still returns tokens

`POST /api/auth/login` can still issue bearer tokens when:

- The request is not detected as stateful (missing/mismatched `Origin` or `Referer`).
- You explicitly pass `issue_token: true`.
- You force token mode with `AUTH_LIBRARY_ISSUE_TOKEN_BY_DEFAULT=true`.

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
