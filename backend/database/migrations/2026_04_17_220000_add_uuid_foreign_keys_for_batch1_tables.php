<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumns();
        $this->backfillData();
        $this->addIndexesAndConstraints();
    }

    public function down(): void
    {
        if (Schema::hasTable('company_users')) {
            Schema::table('company_users', function (Blueprint $table): void {
                if (Schema::hasColumn('company_users', 'invited_by_user_uuid')) {
                    $table->dropColumn('invited_by_user_uuid');
                }
                if (Schema::hasColumn('company_users', 'user_uuid')) {
                    $table->dropColumn('user_uuid');
                }
                if (Schema::hasColumn('company_users', 'company_uuid')) {
                    $table->dropColumn('company_uuid');
                }
                if (Schema::hasColumn('company_users', 'uuid')) {
                    $table->dropColumn('uuid');
                }
            });
        }

        if (Schema::hasTable('employee_profiles')) {
            Schema::table('employee_profiles', function (Blueprint $table): void {
                if (Schema::hasColumn('employee_profiles', 'manager_user_uuid')) {
                    $table->dropColumn('manager_user_uuid');
                }
                if (Schema::hasColumn('employee_profiles', 'company_uuid')) {
                    $table->dropColumn('company_uuid');
                }
                if (Schema::hasColumn('employee_profiles', 'user_uuid')) {
                    $table->dropColumn('user_uuid');
                }
            });
        }

        if (Schema::hasTable('hcm_user_roles')) {
            Schema::table('hcm_user_roles', function (Blueprint $table): void {
                if (Schema::hasColumn('hcm_user_roles', 'assigned_by_user_uuid')) {
                    $table->dropColumn('assigned_by_user_uuid');
                }
                if (Schema::hasColumn('hcm_user_roles', 'company_uuid')) {
                    $table->dropColumn('company_uuid');
                }
                if (Schema::hasColumn('hcm_user_roles', 'user_uuid')) {
                    $table->dropColumn('user_uuid');
                }
            });
        }

        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_uuid')) {
            Schema::table('sessions', function (Blueprint $table): void {
                $table->dropColumn('user_uuid');
            });
        }
    }

    private function addColumns(): void
    {
        if (Schema::hasTable('company_users')) {
            Schema::table('company_users', function (Blueprint $table): void {
                if (! Schema::hasColumn('company_users', 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }
                if (! Schema::hasColumn('company_users', 'company_uuid')) {
                    if (Schema::hasColumn('company_users', 'company_id')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    } else {
                        $table->uuid('company_uuid')->nullable();
                    }
                }
                if (! Schema::hasColumn('company_users', 'user_uuid')) {
                    if (Schema::hasColumn('company_users', 'user_id')) {
                        $table->uuid('user_uuid')->nullable()->after('user_id');
                    } else {
                        $table->uuid('user_uuid')->nullable();
                    }
                }
                if (! Schema::hasColumn('company_users', 'invited_by_user_uuid')) {
                    if (Schema::hasColumn('company_users', 'invited_by_user_id')) {
                        $table->uuid('invited_by_user_uuid')->nullable()->after('invited_by_user_id');
                    } else {
                        $table->uuid('invited_by_user_uuid')->nullable();
                    }
                }
            });
        }

        if (Schema::hasTable('employee_profiles')) {
            Schema::table('employee_profiles', function (Blueprint $table): void {
                if (! Schema::hasColumn('employee_profiles', 'company_uuid')) {
                    if (Schema::hasColumn('employee_profiles', 'company_id')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    } else {
                        $table->uuid('company_uuid')->nullable();
                    }
                }
                if (! Schema::hasColumn('employee_profiles', 'user_uuid')) {
                    if (Schema::hasColumn('employee_profiles', 'user_id')) {
                        $table->uuid('user_uuid')->nullable()->after('user_id');
                    } else {
                        $table->uuid('user_uuid')->nullable();
                    }
                }
                if (! Schema::hasColumn('employee_profiles', 'manager_user_uuid')) {
                    if (Schema::hasColumn('employee_profiles', 'manager_user_id')) {
                        $table->uuid('manager_user_uuid')->nullable()->after('manager_user_id');
                    } else {
                        $table->uuid('manager_user_uuid')->nullable();
                    }
                }
            });
        }

        if (Schema::hasTable('hcm_user_roles')) {
            Schema::table('hcm_user_roles', function (Blueprint $table): void {
                if (! Schema::hasColumn('hcm_user_roles', 'company_uuid')) {
                    if (Schema::hasColumn('hcm_user_roles', 'company_id')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    } else {
                        $table->uuid('company_uuid')->nullable();
                    }
                }
                if (! Schema::hasColumn('hcm_user_roles', 'user_uuid')) {
                    if (Schema::hasColumn('hcm_user_roles', 'user_id')) {
                        $table->uuid('user_uuid')->nullable()->after('user_id');
                    } else {
                        $table->uuid('user_uuid')->nullable();
                    }
                }
                if (! Schema::hasColumn('hcm_user_roles', 'assigned_by_user_uuid')) {
                    if (Schema::hasColumn('hcm_user_roles', 'assigned_by_user_id')) {
                        $table->uuid('assigned_by_user_uuid')->nullable()->after('assigned_by_user_id');
                    } else {
                        $table->uuid('assigned_by_user_uuid')->nullable();
                    }
                }
            });
        }

        if (Schema::hasTable('sessions') && ! Schema::hasColumn('sessions', 'user_uuid')) {
            Schema::table('sessions', function (Blueprint $table): void {
                $table->uuid('user_uuid')->nullable()->after('user_id');
            });
        }
    }

    private function backfillData(): void
    {
        // Backfill row UUID on company_users table.
        if (Schema::hasTable('company_users') && Schema::hasColumn('company_users', 'uuid')) {
            DB::table('company_users')
                ->whereNull('uuid')
                ->orderBy('id')
                ->select('id')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('company_users')
                            ->where('id', $row->id)
                            ->update(['uuid' => (string) Str::uuid()]);
                    }
                }, 'id');
        }

        // Backfill FK UUID references.
        $this->updateFromUsers('company_users', 'user_id', 'user_uuid');
        $this->updateFromUsers('company_users', 'invited_by_user_id', 'invited_by_user_uuid');
        $this->updateFromCompanies('company_users', 'company_id', 'company_uuid');

        $this->updateFromUsers('employee_profiles', 'user_id', 'user_uuid');
        $this->updateFromUsers('employee_profiles', 'manager_user_id', 'manager_user_uuid');
        $this->updateFromCompanies('employee_profiles', 'company_id', 'company_uuid');

        $this->updateFromUsers('hcm_user_roles', 'user_id', 'user_uuid');
        $this->updateFromUsers('hcm_user_roles', 'assigned_by_user_id', 'assigned_by_user_uuid');
        $this->updateFromCompanies('hcm_user_roles', 'company_id', 'company_uuid');

        if (Schema::hasTable('sessions')) {
            $this->updateFromUsers('sessions', 'user_id', 'user_uuid');
        }
    }

    private function addIndexesAndConstraints(): void
    {
        $this->safeIndex('company_users', 'company_uuid', 'company_users_company_uuid_idx');
        $this->safeIndex('company_users', 'user_uuid', 'company_users_user_uuid_idx');
        $this->safeIndex('company_users', 'invited_by_user_uuid', 'company_users_invited_by_user_uuid_idx');

        $this->safeIndex('employee_profiles', 'company_uuid', 'employee_profiles_company_uuid_idx');
        $this->safeUnique('employee_profiles', 'user_uuid', 'employee_profiles_user_uuid_unique');
        $this->safeIndex('employee_profiles', 'manager_user_uuid', 'employee_profiles_manager_user_uuid_idx');

        $this->safeIndex('hcm_user_roles', 'company_uuid', 'hcm_user_roles_company_uuid_idx');
        $this->safeIndex('hcm_user_roles', 'user_uuid', 'hcm_user_roles_user_uuid_idx');
        $this->safeIndex('hcm_user_roles', 'assigned_by_user_uuid', 'hcm_user_roles_assigned_by_user_uuid_idx');

        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table): void {
                $table->index('user_uuid', 'sessions_user_uuid_idx');
            });
        }

        $this->safeForeign('company_users', 'company_uuid', 'companies', 'uuid', 'company_users_company_uuid_fk', 'cascade');
        $this->safeForeign('company_users', 'user_uuid', 'users', 'uuid', 'company_users_user_uuid_fk', 'cascade');
        $this->safeForeign('company_users', 'invited_by_user_uuid', 'users', 'uuid', 'company_users_invited_by_user_uuid_fk', 'null');

        $this->safeForeign('employee_profiles', 'company_uuid', 'companies', 'uuid', 'employee_profiles_company_uuid_fk', 'null');
        $this->safeForeign('employee_profiles', 'user_uuid', 'users', 'uuid', 'employee_profiles_user_uuid_fk', 'cascade');
        $this->safeForeign('employee_profiles', 'manager_user_uuid', 'users', 'uuid', 'employee_profiles_manager_user_uuid_fk', 'null');

        $this->safeForeign('hcm_user_roles', 'company_uuid', 'companies', 'uuid', 'hcm_user_roles_company_uuid_fk', 'cascade');
        $this->safeForeign('hcm_user_roles', 'user_uuid', 'users', 'uuid', 'hcm_user_roles_user_uuid_fk', 'cascade');
        $this->safeForeign('hcm_user_roles', 'assigned_by_user_uuid', 'users', 'uuid', 'hcm_user_roles_assigned_by_user_uuid_fk', 'null');
    }

    private function safeIndex(string $table, string $column, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($column, $name): void {
                $tableBlueprint->index($column, $name);
            });
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }

    private function safeUnique(string $table, string $column, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($column, $name): void {
                $tableBlueprint->unique($column, $name);
            });
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }

    private function safeForeign(string $table, string $column, string $parentTable, string $parentColumn, string $name, string $onDelete): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($column, $parentTable, $parentColumn, $name, $onDelete): void {
                $foreign = $tableBlueprint->foreign($column, $name)->references($parentColumn)->on($parentTable);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } elseif ($onDelete === 'null') {
                    $foreign->nullOnDelete();
                }
            });
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }

    private function updateFromUsers(string $table, string $legacyIdColumn, string $uuidColumn): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, $legacyIdColumn) || ! Schema::hasColumn($table, $uuidColumn)) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("UPDATE {$table} t JOIN users u ON t.{$legacyIdColumn} = u.id SET t.{$uuidColumn} = u.uuid WHERE t.{$legacyIdColumn} IS NOT NULL AND t.{$uuidColumn} IS NULL");

            return;
        }

        $rows = DB::table($table)
            ->whereNotNull($legacyIdColumn)
            ->whereNull($uuidColumn)
            ->select('id', $legacyIdColumn)
            ->get();

        foreach ($rows as $row) {
            $uuid = DB::table('users')->where('id', $row->{$legacyIdColumn})->value('uuid');
            DB::table($table)->where('id', $row->id)->update([$uuidColumn => $uuid]);
        }
    }

    private function updateFromCompanies(string $table, string $legacyIdColumn, string $uuidColumn): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, $legacyIdColumn) || ! Schema::hasColumn($table, $uuidColumn)) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("UPDATE {$table} t JOIN companies c ON t.{$legacyIdColumn} = c.id SET t.{$uuidColumn} = c.uuid WHERE t.{$legacyIdColumn} IS NOT NULL AND t.{$uuidColumn} IS NULL");

            return;
        }

        $rows = DB::table($table)
            ->whereNotNull($legacyIdColumn)
            ->whereNull($uuidColumn)
            ->select('id', $legacyIdColumn)
            ->get();

        foreach ($rows as $row) {
            $uuid = DB::table('companies')->where('id', $row->{$legacyIdColumn})->value('uuid');
            DB::table($table)->where('id', $row->id)->update([$uuidColumn => $uuid]);
        }
    }
};
