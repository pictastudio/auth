<?php

namespace PictaStudio\Auth\Support;

class Authorization
{
    public function __construct(private PermissionNameResolver $resolver) {}

    public function allows(mixed $user, string|object $model, string $action): bool
    {
        if ($user === null || !method_exists($user, 'can')) {
            return false;
        }

        return (bool) $user->can($this->resolver->permissionName($model, $action));
    }
}
