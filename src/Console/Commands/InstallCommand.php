<?php

namespace PictaStudio\Auth\Console\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    protected $signature = 'auth:install';

    protected $description = 'Install auth package';

    public function handle(): int
    {
        $this->components->info('Installing auth package...');

        $this->components->info('Publishing auth configuration...');
        $this->call('vendor:publish', ['--tag' => 'auth-config']);

        $this->components->info('Publishing cors configuration...');
        $this->call('config:publish', ['name' => 'cors']);

        if (confirm('Do you want to publish bruno api files?', false)) {
            $this->components->info('Publishing bruno api files...');
            $this->call('vendor:publish', ['--tag' => 'auth-bruno']);
        }

        $this->components->info('Installing laravel sanctum...');
        $this->call('install:api');

        $this->components->info('Installing spatie permissions...');
        $this->call('vendor:publish', ['--provider' => 'Spatie\Permission\PermissionServiceProvider']);

        $this->components->info('Add HasAuthFeatures trait to your User model...');

        return self::SUCCESS;
    }
}
