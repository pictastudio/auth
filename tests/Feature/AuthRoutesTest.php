<?php

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;
use PictaStudio\Auth\Tests\Support\Models\User;
use PictaStudio\Auth\Tests\Support\Models\UserWithoutSanctum;
use PictaStudio\Auth\Tests\Support\Models\UserWithoutVerification;

use function Pest\Laravel\{postJson, getJson, withHeader};

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
        ->assertJsonPath('user.id', $user->id);

    expect($response->json('token'))->toBeString()->not->toBeEmpty();

    $token = PersonalAccessToken::query()->first();

    expect($token)->not->toBeNull()
        ->and($token?->tokenable_id)->toBe($user->id)
        ->and($token?->tokenable_type)->toBe(User::class)
        ->and($token?->name)->toBe('mobile-device');
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
        ->assertJsonPath('user.id', $user->id)
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
        'id' => $user->id,
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
        'id' => $user->id,
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
        'id' => $user->id,
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
        'id' => $user->id,
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    withHeader('Authorization', "Bearer {$token}")
        ->getJson($url)
        ->assertOk()
        ->assertJsonPath('message', 'Email verified.');

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});
