<?php

namespace PictaStudio\Auth\Console;

use Illuminate\Console\Command;
use PictaStudio\Auth\Actions\GeneratePermissionsAction;

class GeneratePermissionsCommand extends Command
{
    protected $signature = 'auth:permissions:generate';

    protected $description = 'Generate permissions and default roles from auth package config';

    public function handle(GeneratePermissionsAction $action): int
    {
        $summary = $action->execute();

        $this->info('Auth permissions generation completed.');
        $this->line('Created permissions: ' . $summary['created_permissions']);
        $this->line('Existing permissions: ' . $summary['existing_permissions']);
        $this->line('Created roles: ' . $summary['created_roles']);
        $this->line('Existing roles: ' . $summary['existing_roles']);
        $this->line('Permissions attached to roles: ' . $summary['attached_permissions_to_roles']);

        return self::SUCCESS;
    }
}
