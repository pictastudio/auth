<?php

use Illuminate\Support\Facades\DB;

it('removes permission pivot foreign keys without losing data and restores them on rollback', function (): void {
    DB::table('permissions')->insert([
        'id' => 1,
        'name' => 'posts:view',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('roles')->insert([
        'id' => 1,
        'name' => 'admin',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('model_has_permissions')->insert([
        'permission_id' => 1,
        'model_type' => 'App\\Models\\User',
        'model_id' => 10,
    ]);

    DB::table('model_has_roles')->insert([
        'role_id' => 1,
        'model_type' => 'App\\Models\\User',
        'model_id' => 10,
    ]);

    DB::table('role_has_permissions')->insert([
        'permission_id' => 1,
        'role_id' => 1,
    ]);

    $migration = require __DIR__ . '/../../database/migrations/remove_permission_table_foreign_keys.php';

    expect(foreignKeyCount('model_has_permissions'))->toBe(1)
        ->and(foreignKeyCount('model_has_roles'))->toBe(1)
        ->and(foreignKeyCount('role_has_permissions'))->toBe(2);

    $migration->up();

    expect(foreignKeyCount('model_has_permissions'))->toBe(0)
        ->and(foreignKeyCount('model_has_roles'))->toBe(0)
        ->and(foreignKeyCount('role_has_permissions'))->toBe(0)
        ->and(DB::table('model_has_permissions')->count())->toBe(1)
        ->and(DB::table('model_has_roles')->count())->toBe(1)
        ->and(DB::table('role_has_permissions')->count())->toBe(1);

    $migration->down();

    expect(foreignKeyCount('model_has_permissions'))->toBe(1)
        ->and(foreignKeyCount('model_has_roles'))->toBe(1)
        ->and(foreignKeyCount('role_has_permissions'))->toBe(2)
        ->and(DB::table('model_has_permissions')->count())->toBe(1)
        ->and(DB::table('model_has_roles')->count())->toBe(1)
        ->and(DB::table('role_has_permissions')->count())->toBe(1);
});

function foreignKeyCount(string $table): int
{
    $escapedTable = str_replace("'", "''", $table);

    return count(DB::select("PRAGMA foreign_key_list('{$escapedTable}')"));
}
