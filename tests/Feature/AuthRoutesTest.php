<?php

use Illuminate\Auth\Notifications\{ResetPassword, VerifyEmail};
use Illuminate\Support\Facades\{Hash, Notification, Password, URL};
use Laravel\Sanctum\PersonalAccessToken;
use PictaStudio\Auth\Tests\Support\Models\{User, UserWithoutSanctum, UserWithoutVerification};

use function Pest\Laravel\{getJson, postJson, withHeader};

it('registers sanctum csrf cookie route under the auth prefix', function (): void {
    expect(route('sanctum.csrf-cookie', [], false))->toBe('/api/auth/csrf-cookie');
});

it('validates login payload', function (): void {
    postJson(route('auth.login'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('rejects invalid login credentials', function (): void {
    User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('old-password'),
    ]);

    postJson(route('auth.login'), [
        'email' => 'john@example.com',
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The provided credentials are invalid.');
});

it('returns an error when the auth model cannot be resolved on login', function (): void {
    config()->set('auth.guards.web.provider', 'missing-provider');

    postJson(route('auth.login'), [
        'email' => 'john@example.com',
        'password' => 'password',
    ])
        ->assertStatus(500)
        ->assertJsonPath('message', 'Unable to resolve the auth model for the configured guard.');
});

it('returns an error when the auth model does not support sanctum tokens on login', function (): void {
    config()->set('auth.providers.users.model', UserWithoutSanctum::class);

    UserWithoutSanctum::query()->create([
        'name' => 'No Token User',
        'email' => 'notoken@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    postJson(route('auth.login'), [
        'email' => 'notoken@example.com',
        'password' => 'secret-password',
    ])
        ->assertStatus(500)
        ->assertJsonPath('message', 'The auth model must use Laravel Sanctum HasApiTokens.');
});

it('logs in and creates a sanctum token', function (): void {
    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $response = postJson(route('auth.login'), [
        'email' => 'john@example.com',
        'password' => 'secret-password',
        'token_name' => 'mobile-device',
    ]);

    $response->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->getKey());

    expect($response->json('token'))->toBeString()->not->toBeEmpty();

    $token = PersonalAccessToken::query()->first();

    expect($token)->not->toBeNull()
        ->and($token?->tokenable_id)->toBe($user->getKey())
        ->and($token?->tokenable_type)->toBe(User::class)
        ->and($token?->name)->toBe('mobile-device');
});

it('logs in with cookie auth by default for stateful frontend requests', function (): void {
    config()->set('sanctum.stateful', ['frontend.test']);

    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    postJson(route('auth.login'), [
        'email' => 'john@example.com',
        'password' => 'secret-password',
    ], [
        'Origin' => 'https://frontend.test',
    ])
        ->assertOk()
        ->assertJsonPath('authenticated_via', 'cookie')
        ->assertJsonPath('user.id', $user->getKey());

    expect(PersonalAccessToken::query()->count())->toBe(0);

    getJson(route('auth.me'), [
        'Origin' => 'https://frontend.test',
    ])
        ->assertOk()
        ->assertJsonPath('user.id', $user->getKey());
});

it('can force token issuance for stateful frontend requests', function (): void {
    config()->set('sanctum.stateful', ['frontend.test']);

    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $response = postJson(route('auth.login'), [
        'email' => 'john@example.com',
        'password' => 'secret-password',
        'issue_token' => true,
        'token_name' => 'frontend-device',
    ], [
        'Origin' => 'https://frontend.test',
    ]);

    $response->assertOk()
        ->assertJsonPath('authenticated_via', 'token')
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->getKey());

    expect($response->json('token'))->toBeString()->not->toBeEmpty()
        ->and(PersonalAccessToken::query()->count())->toBe(1);
});

it('requires authentication on me route', function (): void {
    getJson(route('auth.me'))
        ->assertUnauthorized();
});

it('returns the authenticated user on me route', function (): void {
    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $token = $user->createToken('api')->plainTextToken;

    withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('auth.me'))
        ->assertOk()
        ->assertJsonPath('user.id', $user->getKey())
        ->assertJsonPath('user.email', 'john@example.com');
});

it('requires authentication on logout route', function (): void {
    postJson(route('auth.logout'))
        ->assertUnauthorized();
});

it('logs out and deletes the current access token', function (): void {
    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $token = $user->createToken('api')->plainTextToken;

    expect(PersonalAccessToken::query()->count())->toBe(1);

    withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('auth.logout'))
        ->assertOk()
        ->assertJsonPath('message', 'Logged out.');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('logs out and invalidates cookie-based sessions', function (): void {
    config()->set('sanctum.stateful', ['frontend.test']);

    User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    postJson(route('auth.login'), [
        'email' => 'john@example.com',
        'password' => 'secret-password',
    ], [
        'Origin' => 'https://frontend.test',
    ])->assertOk();

    postJson(route('auth.logout'), [], [
        'Origin' => 'https://frontend.test',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Logged out.');

    expect(auth()->guard('web')->check())->toBeFalse();
});

it('validates forgot password payload', function (): void {
    postJson(route('auth.password.email'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('sends a password reset link for existing users', function (): void {
    Notification::fake();

    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    postJson(route('auth.password.email'), [
        'email' => 'john@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('message', __(Password::RESET_LINK_SENT));

    Notification::assertSentTo($user, ResetPassword::class);
});

it('builds reset password notification links from frontend route config', function (): void {
    Notification::fake();

    config()->set('picta-auth.frontend_urls.reset_password', 'https://frontend.example/reset-password');

    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    postJson(route('auth.password.email'), [
        'email' => 'john@example.com',
    ])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $mailMessage = $notification->toMail($user);
        $actionUrl = $mailMessage->actionUrl;
        $query = [];

        parse_str((string) parse_url($actionUrl, PHP_URL_QUERY), $query);

        expect($actionUrl)->toStartWith('https://frontend.example/reset-password?')
            ->and($query)->toHaveKeys(['token', 'email'])
            ->and($query['email'])->toBe('john@example.com')
            ->and($query['token'])->toBeString()->not->toBeEmpty();

        return true;
    });
});

it('builds reset password notification links without configured frontend route', function (): void {
    Notification::fake();

    config()->set('picta-auth.frontend_urls.reset_password', null);
    config()->set('picta-auth.routes.default_reset_password_path', '/reset-password');

    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    postJson(route('auth.password.email'), [
        'email' => 'john@example.com',
    ])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $mailMessage = $notification->toMail($user);
        $actionUrl = $mailMessage->actionUrl;
        $query = [];

        parse_str((string) parse_url($actionUrl, PHP_URL_QUERY), $query);

        expect($query)->toHaveKeys(['token', 'email'])
            ->and($query['email'])->toBe('john@example.com')
            ->and($query['token'])->toBeString()->not->toBeEmpty();

        if (!app('router')->has('password.reset')) {
            expect($actionUrl)->toStartWith(mb_rtrim((string) config('app.url'), '/') . '/reset-password?');
        }

        return true;
    });
});

it('returns an error when forgot password email does not exist', function (): void {
    Notification::fake();

    postJson(route('auth.password.email'), [
        'email' => 'missing@example.com',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __(Password::INVALID_USER));

    Notification::assertNothingSent();
});

it('validates reset password payload', function (): void {
    postJson(route('auth.password.reset'), [
        'email' => 'john@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

it('uses configurable password reset validation rules', function (): void {
    config()->set('picta-auth.password_rules', ['required', 'string', 'confirmed', 'min:12']);

    postJson(route('auth.password.reset'), [
        'token' => 'some-token',
        'email' => 'john@example.com',
        'password' => 'short-pass',
        'password_confirmation' => 'short-pass',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('resets password with a valid reset token', function (): void {
    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('old-password'),
    ]);

    $token = Password::broker('users')->createToken($user);

    postJson(route('auth.password.reset'), [
        'token' => $token,
        'email' => 'john@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])
        ->assertOk()
        ->assertJsonPath('message', __(Password::PASSWORD_RESET));

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

it('returns an error when reset password token is invalid', function (): void {
    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('old-password'),
    ]);

    postJson(route('auth.password.reset'), [
        'token' => 'invalid-token',
        'email' => 'john@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __(Password::INVALID_TOKEN));

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('requires authentication on verification notification route', function (): void {
    postJson(route('auth.verification.send'))
        ->assertUnauthorized();
});

it('sends email verification notification for unverified users', function (): void {
    Notification::fake();

    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
        'email_verified_at' => null,
    ]);

    $token = $user->createToken('api')->plainTextToken;

    withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('auth.verification.send'))
        ->assertOk()
        ->assertJsonPath('message', 'Verification link sent.');

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('builds email verification notification links from frontend route config', function (): void {
    Notification::fake();

    config()->set('picta-auth.frontend_urls.email_verification', 'https://frontend.example/verify-email');

    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
        'email_verified_at' => null,
    ]);

    $token = $user->createToken('api')->plainTextToken;

    withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('auth.verification.send'))
        ->assertOk();

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
        $mailMessage = $notification->toMail($user);
        $actionUrl = $mailMessage->actionUrl;
        $query = [];

        parse_str((string) parse_url($actionUrl, PHP_URL_QUERY), $query);

        expect($actionUrl)->toStartWith('https://frontend.example/verify-email?')
            ->and($query)->toHaveKeys(['id', 'hash', 'expires', 'signature'])
            ->and($query['id'])->toBe((string) $user->getKey())
            ->and($query['hash'])->toBe(sha1($user->getEmailForVerification()));

        return true;
    });
});

it('returns early when user email is already verified', function (): void {
    Notification::fake();

    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
        'email_verified_at' => now(),
    ]);

    $token = $user->createToken('api')->plainTextToken;

    withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('auth.verification.send'))
        ->assertOk()
        ->assertJsonPath('message', 'Email already verified.');

    Notification::assertNothingSent();
});

it('returns an error when auth model does not provide email verification behavior', function (): void {
    $user = UserWithoutVerification::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $token = $user->createToken('api')->plainTextToken;

    withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('auth.verification.send'))
        ->assertStatus(500)
        ->assertJsonPath('message', 'Email verification is not available for this model.');
});

it('requires authentication on verify email route', function (): void {
    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $url = URL::temporarySignedRoute('auth.verification.verify', now()->addMinutes(5), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    getJson($url)
        ->assertUnauthorized();
});

it('rejects verify email requests with invalid signatures', function (): void {
    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $token = $user->createToken('api')->plainTextToken;

    $unsignedUrl = route('auth.verification.verify', [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    withHeader('Authorization', "Bearer {$token}")
        ->getJson($unsignedUrl)
        ->assertForbidden();
});

it('rejects verify email requests when the hash does not match the user email', function (): void {
    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $token = $user->createToken('api')->plainTextToken;

    $url = URL::temporarySignedRoute('auth.verification.verify', now()->addMinutes(5), [
        'id' => $user->getKey(),
        'hash' => sha1('wrong@example.com'),
    ]);

    withHeader('Authorization', "Bearer {$token}")
        ->getJson($url)
        ->assertForbidden();
});

it('verifies email from a valid signed request', function (): void {
    $user = User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
        'email_verified_at' => null,
    ]);

    $token = $user->createToken('api')->plainTextToken;

    $url = URL::temporarySignedRoute('auth.verification.verify', now()->addMinutes(5), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    withHeader('Authorization', "Bearer {$token}")
        ->getJson($url)
        ->assertOk()
        ->assertJsonPath('message', 'Email verified.');

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});
