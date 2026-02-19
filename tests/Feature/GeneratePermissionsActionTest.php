<?php

use PictaStudio\Auth\Actions\GeneratePermissionsAction;
use PictaStudio\Auth\Tests\Support\Models\{Post, User};
use Spatie\Permission\Models\{Permission, Role};

if (!class_exists(Permission::class) || !class_exists(Role::class) || !trait_exists('Spatie\\Permission\\Traits\\HasRoles')) {
    test('spatie/laravel-permission is required for permission tests', function (): void {
        $this->markTestSkipped('Install spatie/laravel-permission to run this test.');
    });

    return;
}

it('generates configured permissions and default roles without duplicating existing records', function (): void {
    config()->set('picta-auth.permissions.models', [
        'post' => Post::class,
        User::class,
    ]);
    config()->set('picta-auth.permissions.actions', ['view', 'create']);

    $summary = app(GeneratePermissionsAction::class)->execute();

    expect($summary['created_permissions'])->toBe(4)
        ->and($summary['created_roles'])->toBe(3)
        ->and(Permission::query()->count())->toBe(4)
        ->and(Role::query()->count())->toBe(3)
        ->and(Role::findByName('root', 'web')->hasPermissionTo('post:view'))->toBeTrue()
        ->and(Role::findByName('admin', 'web')->hasPermissionTo('user:create'))->toBeTrue()
        ->and(Role::findByName('user', 'web')->permissions()->count())->toBe(0);

    $secondRun = app(GeneratePermissionsAction::class)->execute();

    expect($secondRun['created_permissions'])->toBe(0)
        ->and($secondRun['existing_permissions'])->toBe(4)
        ->and($secondRun['created_roles'])->toBe(0)
        ->and($secondRun['existing_roles'])->toBe(3)
        ->and(Permission::query()->count())->toBe(4)
        ->and(Role::query()->count())->toBe(3);
});

it('authorizes using the helper with explicit and default authenticated user', function (): void {
    config()->set('picta-auth.permissions.models', [
        'post' => Post::class,
    ]);
    config()->set('picta-auth.permissions.actions', ['view', 'delete']);

    app(GeneratePermissionsAction::class)->execute();

    $user = User::query()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => bcrypt('secret'),
    ]);

    $user->givePermissionTo('post:view');

    expect(auth_authorize(Post::class, 'view', $user))->toBeTrue()
        ->and(auth_authorize(Post::class, 'delete', $user))->toBeFalse()
        ->and($user->canAuthorize(Post::class, 'view'))->toBeTrue();

    auth()->guard()->login($user);

    expect(auth_authorize(Post::class, 'view'))->toBeTrue();
});
