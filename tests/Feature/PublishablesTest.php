<?php

use Illuminate\Support\ServiceProvider;
use PictaStudio\Auth\AuthServiceProvider;

it('publishes personal access tokens migration', function (): void {
    $paths = ServiceProvider::pathsToPublish(AuthServiceProvider::class, 'auth-migrations');
    $matches = collect($paths)->filter(
        fn (string $destination, string $source): bool => str_ends_with($source, '/database/migrations/create_personal_access_tokens_table.php')
            && str_starts_with($destination, database_path('migrations/'))
            && str_ends_with($destination, '_create_personal_access_tokens_table.php')
    );

    expect($paths)->toBeArray()
        ->and($matches)->toHaveCount(1);
});
