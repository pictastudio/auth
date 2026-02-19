<?php

namespace PictaStudio\Auth\Actions;

use PictaStudio\Auth\Support\PermissionNameResolver;
use Spatie\Permission\Models\{Permission, Role};

class GeneratePermissionsAction
{
    public function __construct(private PermissionNameResolver $resolver) {}

    /**
     * @return array{
     *     created_permissions: int,
     *     existing_permissions: int,
     *     created_roles: int,
     *     existing_roles: int,
     *     attached_permissions_to_roles: int,
     *     permissions: list<string>
     * }
     */
    public function execute(): array
    {
        $guard = $this->resolver->guard();
        $actions = $this->resolver->actions();
        $models = $this->resolver->modelNames();

        $createdPermissions = 0;
        $existingPermissions = 0;
        $permissionNames = [];

        foreach ($models as $modelName) {
            foreach ($actions as $action) {
                $name = $this->resolver->permissionName($modelName, $action);
                $existing = Permission::query()
                    ->where('name', $name)
                    ->where('guard_name', $guard)
                    ->exists();

                if ($existing) {
                    $existingPermissions++;
                    $permissionNames[] = $name;

                    continue;
                }

                Permission::query()->create([
                    'name' => $name,
                    'guard_name' => $guard,
                ]);

                $createdPermissions++;
                $permissionNames[] = $name;
            }
        }

        $createdRoles = 0;
        $existingRoles = 0;
        $attachedToRoles = 0;

        foreach ($this->rolesFromConfig() as $roleName => $config) {
            $roleExists = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', $guard)
                ->exists();

            if ($roleExists) {
                $existingRoles++;
            } else {
                $createdRoles++;
            }

            $role = Role::findOrCreate($roleName, $guard);

            if (!($config['all_permissions'] ?? false)) {
                continue;
            }

            $currentPermissions = $role->permissions()->pluck('name')->all();
            $missingPermissions = array_values(array_diff($permissionNames, $currentPermissions));

            if ($missingPermissions === []) {
                continue;
            }

            $role->givePermissionTo($missingPermissions);
            $attachedToRoles += count($missingPermissions);
        }

        return [
            'created_permissions' => $createdPermissions,
            'existing_permissions' => $existingPermissions,
            'created_roles' => $createdRoles,
            'existing_roles' => $existingRoles,
            'attached_permissions_to_roles' => $attachedToRoles,
            'permissions' => $permissionNames,
        ];
    }

    /**
     * @return array<string, array{all_permissions: bool}>
     */
    private function rolesFromConfig(): array
    {
        $configured = config('auth.library.roles', []);

        if (!is_array($configured)) {
            return [];
        }

        $roles = [];

        foreach ($configured as $roleName => $roleConfig) {
            if (!is_string($roleName) || $roleName === '') {
                continue;
            }

            $allPermissions = is_array($roleConfig) && isset($roleConfig['all_permissions'])
                ? (bool) $roleConfig['all_permissions']
                : false;

            $roles[$roleName] = [
                'all_permissions' => $allPermissions,
            ];
        }

        return $roles;
    }
}
