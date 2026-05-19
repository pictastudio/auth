<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        $this->rebuildPermissionPivotTables(withForeignKeys: false);
    }

    public function down(): void
    {
        $this->rebuildPermissionPivotTables(withForeignKeys: true);
    }

    private function rebuildPermissionPivotTables(bool $withForeignKeys): void
    {
        $tableNames = $this->tableNames();
        $columnNames = $this->columnNames();
        $teams = (bool) config('permission.teams', false);

        $this->rebuildModelHasPermissionsTable(
            tableName: $tableNames['model_has_permissions'],
            permissionsTable: $tableNames['permissions'],
            permissionPivotKey: $columnNames['permission_pivot_key'],
            modelMorphKey: $columnNames['model_morph_key'],
            teamForeignKey: $columnNames['team_foreign_key'],
            teams: $teams,
            withForeignKeys: $withForeignKeys,
        );

        $this->rebuildModelHasRolesTable(
            tableName: $tableNames['model_has_roles'],
            rolesTable: $tableNames['roles'],
            rolePivotKey: $columnNames['role_pivot_key'],
            modelMorphKey: $columnNames['model_morph_key'],
            teamForeignKey: $columnNames['team_foreign_key'],
            teams: $teams,
            withForeignKeys: $withForeignKeys,
        );

        $this->rebuildRoleHasPermissionsTable(
            tableName: $tableNames['role_has_permissions'],
            permissionsTable: $tableNames['permissions'],
            rolesTable: $tableNames['roles'],
            permissionPivotKey: $columnNames['permission_pivot_key'],
            rolePivotKey: $columnNames['role_pivot_key'],
            withForeignKeys: $withForeignKeys,
        );

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    private function rebuildModelHasPermissionsTable(
        string $tableName,
        string $permissionsTable,
        string $permissionPivotKey,
        string $modelMorphKey,
        string $teamForeignKey,
        bool $teams,
        bool $withForeignKeys,
    ): void {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        $columns = [$permissionPivotKey, 'model_type', $modelMorphKey];

        if ($teams) {
            array_unshift($columns, $teamForeignKey);
        }

        $this->rebuildTable($tableName, $columns, function (string $temporaryTable) use (
            $modelMorphKey,
            $permissionPivotKey,
            $permissionsTable,
            $teamForeignKey,
            $teams,
            $withForeignKeys
        ): void {
            Schema::create($temporaryTable, function (Blueprint $table) use (
                $modelMorphKey,
                $permissionPivotKey,
                $permissionsTable,
                $teamForeignKey,
                $teams,
                $withForeignKeys
            ): void {
                if ($teams) {
                    $table->unsignedBigInteger($teamForeignKey);
                    $table->index($teamForeignKey);
                }

                $table->unsignedBigInteger($permissionPivotKey);
                $table->string('model_type');
                $table->unsignedBigInteger($modelMorphKey);
                $table->index([$modelMorphKey, 'model_type']);

                if ($withForeignKeys) {
                    $table->foreign($permissionPivotKey)
                        ->references('id')
                        ->on($permissionsTable)
                        ->cascadeOnDelete();
                }

                if ($teams) {
                    $table->primary([$teamForeignKey, $permissionPivotKey, $modelMorphKey, 'model_type']);

                    return;
                }

                $table->primary([$permissionPivotKey, $modelMorphKey, 'model_type']);
            });
        });
    }

    private function rebuildModelHasRolesTable(
        string $tableName,
        string $rolesTable,
        string $rolePivotKey,
        string $modelMorphKey,
        string $teamForeignKey,
        bool $teams,
        bool $withForeignKeys,
    ): void {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        $columns = [$rolePivotKey, 'model_type', $modelMorphKey];

        if ($teams) {
            array_unshift($columns, $teamForeignKey);
        }

        $this->rebuildTable($tableName, $columns, function (string $temporaryTable) use (
            $modelMorphKey,
            $rolePivotKey,
            $rolesTable,
            $teamForeignKey,
            $teams,
            $withForeignKeys
        ): void {
            Schema::create($temporaryTable, function (Blueprint $table) use (
                $modelMorphKey,
                $rolePivotKey,
                $rolesTable,
                $teamForeignKey,
                $teams,
                $withForeignKeys
            ): void {
                if ($teams) {
                    $table->unsignedBigInteger($teamForeignKey);
                    $table->index($teamForeignKey);
                }

                $table->unsignedBigInteger($rolePivotKey);
                $table->string('model_type');
                $table->unsignedBigInteger($modelMorphKey);
                $table->index([$modelMorphKey, 'model_type']);

                if ($withForeignKeys) {
                    $table->foreign($rolePivotKey)
                        ->references('id')
                        ->on($rolesTable)
                        ->cascadeOnDelete();
                }

                if ($teams) {
                    $table->primary([$teamForeignKey, $rolePivotKey, $modelMorphKey, 'model_type']);

                    return;
                }

                $table->primary([$rolePivotKey, $modelMorphKey, 'model_type']);
            });
        });
    }

    private function rebuildRoleHasPermissionsTable(
        string $tableName,
        string $permissionsTable,
        string $rolesTable,
        string $permissionPivotKey,
        string $rolePivotKey,
        bool $withForeignKeys,
    ): void {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        $columns = [$permissionPivotKey, $rolePivotKey];

        $this->rebuildTable($tableName, $columns, function (string $temporaryTable) use (
            $permissionPivotKey,
            $permissionsTable,
            $rolePivotKey,
            $rolesTable,
            $withForeignKeys
        ): void {
            Schema::create($temporaryTable, function (Blueprint $table) use (
                $permissionPivotKey,
                $permissionsTable,
                $rolePivotKey,
                $rolesTable,
                $withForeignKeys
            ): void {
                $table->unsignedBigInteger($permissionPivotKey);
                $table->unsignedBigInteger($rolePivotKey);

                if ($withForeignKeys) {
                    $table->foreign($permissionPivotKey)
                        ->references('id')
                        ->on($permissionsTable)
                        ->cascadeOnDelete();

                    $table->foreign($rolePivotKey)
                        ->references('id')
                        ->on($rolesTable)
                        ->cascadeOnDelete();
                }

                $table->primary([$permissionPivotKey, $rolePivotKey]);
            });
        });
    }

    /**
     * @param  list<string>  $columns
     * @param  callable(string): void  $createTemporaryTable
     */
    private function rebuildTable(string $tableName, array $columns, callable $createTemporaryTable): void
    {
        $temporaryTable = $this->temporaryTableName($tableName);

        $originalDropped = false;

        try {
            $createTemporaryTable($temporaryTable);

            DB::table($temporaryTable)->insertUsing(
                $columns,
                DB::table($tableName)->select($columns)
            );

            Schema::drop($tableName);
            $originalDropped = true;

            Schema::rename($temporaryTable, $tableName);
        } catch (Throwable $exception) {
            if ($originalDropped && Schema::hasTable($temporaryTable) && !Schema::hasTable($tableName)) {
                Schema::rename($temporaryTable, $tableName);
            } else {
                Schema::dropIfExists($temporaryTable);
            }

            throw $exception;
        }
    }

    private function temporaryTableName(string $tableName): string
    {
        do {
            $temporaryTable = sprintf(
                '%s_fk_%s',
                $tableName,
                mb_substr(md5(uniqid((string) mt_rand(), true)), 0, 8)
            );
        } while (Schema::hasTable($temporaryTable));

        return $temporaryTable;
    }

    /**
     * @return array{
     *     permissions: string,
     *     roles: string,
     *     model_has_permissions: string,
     *     model_has_roles: string,
     *     role_has_permissions: string
     * }
     */
    private function tableNames(): array
    {
        $configured = config('permission.table_names', []);

        return [
            'permissions' => (string) ($configured['permissions'] ?? 'permissions'),
            'roles' => (string) ($configured['roles'] ?? 'roles'),
            'model_has_permissions' => (string) ($configured['model_has_permissions'] ?? 'model_has_permissions'),
            'model_has_roles' => (string) ($configured['model_has_roles'] ?? 'model_has_roles'),
            'role_has_permissions' => (string) ($configured['role_has_permissions'] ?? 'role_has_permissions'),
        ];
    }

    /**
     * @return array{
     *     permission_pivot_key: string,
     *     role_pivot_key: string,
     *     model_morph_key: string,
     *     team_foreign_key: string
     * }
     */
    private function columnNames(): array
    {
        $configured = config('permission.column_names', []);

        return [
            'permission_pivot_key' => (string) ($configured['permission_pivot_key'] ?? 'permission_id'),
            'role_pivot_key' => (string) ($configured['role_pivot_key'] ?? 'role_id'),
            'model_morph_key' => (string) ($configured['model_morph_key'] ?? 'model_id'),
            'team_foreign_key' => (string) ($configured['team_foreign_key'] ?? 'team_id'),
        ];
    }
};
