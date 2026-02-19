<?php

namespace PictaStudio\Auth\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

class PermissionNameResolver
{
    /**
     * @return list<string>
     */
    public function actions(): array
    {
        $actions = config('auth.library.permissions.actions', []);

        return array_values(array_unique(array_filter($actions, fn (mixed $action): bool => is_string($action) && $action !== '')));
    }

    /**
     * @return list<string>
     */
    public function modelNames(): array
    {
        $models = config('auth.library.permissions.models', []);
        $names = [];

        foreach ($models as $key => $value) {
            if (is_int($key)) {
                if (! is_string($value) || $value === '') {
                    continue;
                }

                $names[] = $this->toModelName($value);

                continue;
            }

            if (is_string($key) && $key !== '') {
                $names[] = Str::of($key)->trim()->lower()->replace('_', '-')->toString();
            }
        }

        return array_values(array_unique(array_filter($names)));
    }

    public function permissionName(string|object $model, string $action): string
    {
        if ($action === '') {
            throw new InvalidArgumentException('Action cannot be empty.');
        }

        return $this->toModelName($model) . $this->delimiter() . Str::of($action)->trim()->lower()->toString();
    }

    public function guard(): string
    {
        return config('auth.library.guard', config('auth.defaults.guard', 'web'));
    }

    private function delimiter(): string
    {
        $delimiter = config('auth.library.permissions.delimiter', ':');

        return is_string($delimiter) && $delimiter !== '' ? $delimiter : ':';
    }

    private function toModelName(string|object $model): string
    {
        $className = is_object($model) ? $model::class : $model;

        $configured = config('auth.library.permissions.models', []);

        foreach ($configured as $alias => $configuredModel) {
            if (! is_string($configuredModel)) {
                continue;
            }

            if (ltrim($configuredModel, '\\') !== ltrim($className, '\\')) {
                continue;
            }

            if (is_string($alias) && $alias !== '') {
                return Str::of($alias)->trim()->lower()->replace('_', '-')->toString();
            }

            return Str::of(class_basename($configuredModel))->snake('-')->toString();
        }

        if (str_contains($className, '\\')) {
            return Str::of(class_basename($className))->snake('-')->toString();
        }

        return Str::of($className)->trim()->lower()->replace('_', '-')->toString();
    }
}
