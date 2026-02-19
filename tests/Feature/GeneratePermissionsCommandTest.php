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
    config()->set('auth.library.permissions.models', ['post']);
    config()->set('auth.library.permissions.actions', ['view']);

    artisan('auth:permissions:generate')
        ->expectsOutput('Auth permissions generation completed.')
        ->assertSuccessful();

    expect(Permission::query()->where('name', 'post:view')->exists())->toBeTrue();
});
