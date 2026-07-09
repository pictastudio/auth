<?php

use Illuminate\Support\Facades\Hash;
use PictaStudio\Auth\Tests\Support\Models\User;
use Spatie\Permission\Models\{Permission, Role};

use function Pest\Laravel\{getJson, withHeader};

function userCrudToken(array $permissions = []): string
{
    static $counter = 0;

    $counter++;

    $user = User::query()->create([
        'name' => 'Admin User',
        'email' => "admin-{$counter}@example.com",
        'password' => Hash::make('secret-password'),
    ]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user->createToken('api')->plainTextToken;
}

it('requires authentication on user index route', function (): void {
    getJson(route('users.index'))
        ->assertUnauthorized();
});

it('denies user routes when the authenticated user lacks the required permission', function (): void {
    $token = userCrudToken();

    withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('users.index'))
        ->assertForbidden();
});

it('returns paginated users for users with view-any permission', function (): void {
    $token = userCrudToken(['user:view-any']);

    User::query()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    User::query()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('users.index', ['per_page' => 2]))
        ->assertOk()
        ->assertJsonPath('per_page', 2)
        ->assertJsonCount(2, 'data');
});

it('validates required password when creating users', function (): void {
    $token = userCrudToken(['user:create']);

    withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('users.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('validates unique email when creating users', function (): void {
    $token = userCrudToken(['user:create']);

    User::query()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('users.store'), [
            'name' => 'Another Jane',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('validates role names when creating users', function (): void {
    $token = userCrudToken(['user:create']);

    withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('users.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'roles' => ['missing-role'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['roles.0']);
});

it('creates users and syncs roles', function (): void {
    $token = userCrudToken(['user:create']);

    Role::findOrCreate('admin', 'web');

    withHeader('Authorization', "Bearer {$token}")
        ->postJson(route('users.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'roles' => ['admin'],
        ])
        ->assertCreated()
        ->assertJsonPath('user.email', 'jane@example.com');

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect(Hash::check('secret-password', $user->password))->toBeTrue()
        ->and($user->hasRole('admin'))->toBeTrue();
});

it('returns a single user for users with view permission', function (): void {
    $token = userCrudToken(['user:view']);

    $user = User::query()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('users.show', ['user' => $user->getKey()]))
        ->assertOk()
        ->assertJsonPath('user.id', $user->getKey())
        ->assertJsonPath('user.email', 'jane@example.com');
});

it('updates users and syncs roles', function (): void {
    $token = userCrudToken(['user:update']);

    Role::findOrCreate('editor', 'web');

    $user = User::query()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => Hash::make('old-password'),
    ]);

    withHeader('Authorization', "Bearer {$token}")
        ->putJson(route('users.update', ['user' => $user->getKey()]), [
            'name' => 'Jane Updated',
            'email' => 'jane.updated@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'roles' => ['editor'],
        ])
        ->assertOk()
        ->assertJsonPath('user.name', 'Jane Updated')
        ->assertJsonPath('user.email', 'jane.updated@example.com');

    $user->refresh();

    expect(Hash::check('new-password', $user->password))->toBeTrue()
        ->and($user->hasRole('editor'))->toBeTrue();
});

it('ignores the current user when validating unique email on update', function (): void {
    $token = userCrudToken(['user:update']);

    $user = User::query()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    withHeader('Authorization', "Bearer {$token}")
        ->patchJson(route('users.update', ['user' => $user->getKey()]), [
            'email' => 'jane@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('user.email', 'jane@example.com');
});

it('deletes users for users with delete permission', function (): void {
    $token = userCrudToken(['user:delete']);

    $user = User::query()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    withHeader('Authorization', "Bearer {$token}")
        ->deleteJson(route('users.destroy', ['user' => $user->getKey()]))
        ->assertNoContent();

    expect(User::query()->whereKey($user->getKey())->exists())->toBeFalse();
});
