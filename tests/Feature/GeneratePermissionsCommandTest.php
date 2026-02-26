<?php

use Spatie\Permission\Models\Permission;

use function Pest\Laravel\artisan;

if (!class_exists(Permission::class)) {
    test('spatie/laravel-permission is required for command tests', function (): void {
        $this->markTestSkipped('Install spatie/laravel-permission to run this test.');
    });

    return;
}

it('runs the command to generate permissions', function (): void {
    config()->set('picta-auth.permissions.models', ['post']);
    config()->set('picta-auth.permissions.actions', ['view']);
    config()->set('picta-auth.roles', [
        'admin' => ['all_permissions' => true],
    ]);

    artisan('auth:permissions:generate')
        ->assertSuccessful()
        ->expectsOutputToContain('Auth permissions generation completed.');

    expect(Permission::query()->where('name', 'post:view')->exists())->toBeTrue();
});
