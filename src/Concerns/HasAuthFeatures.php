<?php

namespace PictaStudio\Auth\Concerns;

use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

trait HasAuthFeatures
{
    use HasApiTokens;
    use HasRoles;
    use Notifiable;

    public function canAuthorize(string|object $model, string $action): bool
    {
        return auth_authorize($model, $action, $this);
    }

    protected function getDefaultGuardName(): string
    {
        return config('auth.library.guard', config('auth.defaults.guard', 'web'));
    }
}
