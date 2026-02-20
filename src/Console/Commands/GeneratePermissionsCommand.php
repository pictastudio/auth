<?php

namespace PictaStudio\Auth\Console\Commands;

use Illuminate\Console\Command;
use PictaStudio\Auth\Actions\GeneratePermissionsAction;

use function Laravel\Prompts\table;

class GeneratePermissionsCommand extends Command
{
    protected $signature = 'auth:permissions:generate';

    protected $description = 'Generate permissions and default roles from auth package config';

    public function handle(GeneratePermissionsAction $action): int
    {
        $summary = $action->execute();

        $this->components->info('Auth permissions generation completed.');

        table(
            [
                'Type',
                'Count',
            ],
            [
                ['Created permissions', $summary['created_permissions']],
                ['Existing permissions', $summary['existing_permissions']],
                ['Created roles', $summary['created_roles']],
                ['Existing roles', $summary['existing_roles']],
                ['Permissions attached to roles', $summary['attached_permissions_to_roles']],
            ]
        );

        return self::SUCCESS;
    }
}
