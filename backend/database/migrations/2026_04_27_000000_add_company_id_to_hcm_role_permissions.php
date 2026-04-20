<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_role_permissions')) {
            return;
        }

        if (! Schema::hasColumn('hcm_role_permissions', 'company_id')) {
            Schema::table('hcm_role_permissions', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->after('permission_id');
            });
        }

        if (! $this->indexExists('hcm_role_permissions', 'hcm_role_permissions_role_id_idx')) {
            Schema::table('hcm_role_permissions', function (Blueprint $table): void {
                $table->index('role_id', 'hcm_role_permissions_role_id_idx');
            });
        }

        if (! $this->indexExists('hcm_role_permissions', 'hcm_role_permissions_company_role_idx')) {
            Schema::table('hcm_role_permissions', function (Blueprint $table): void {
                $table->index(['company_id', 'role_id'], 'hcm_role_permissions_company_role_idx');
            });
        }

        if (! $this->indexExists('hcm_role_permissions', 'hcm_role_permissions_company_permission_idx')) {
            Schema::table('hcm_role_permissions', function (Blueprint $table): void {
                $table->index(['company_id', 'permission_id'], 'hcm_role_permissions_company_permission_idx');
            });
        }

        if (Schema::hasColumn('hcm_role_permissions', 'company_id')) {
            DB::statement('UPDATE hcm_role_permissions rp JOIN hcm_roles r ON r.id = rp.role_id SET rp.company_id = r.company_id WHERE rp.company_id IS NULL');
        }

        $this->tryAddCompanyIdForeignKey();

        try {
            if ($this->indexExists('hcm_role_permissions', 'hcm_role_permissions_unique')) {
                Schema::table('hcm_role_permissions', function (Blueprint $table): void {
                    $table->dropUnique('hcm_role_permissions_unique');
                });
            }
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'doesn\'t exist') === false && stripos($e->getMessage(), 'can\'t drop') === false) {
                throw $e;
            }
        }

        try {
            if (! $this->indexExists('hcm_role_permissions', 'hcm_role_permissions_tenant_unique')) {
                Schema::table('hcm_role_permissions', function (Blueprint $table): void {
                    $table->unique(['company_id', 'role_id', 'permission_id'], 'hcm_role_permissions_tenant_unique');
                });
            }
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('hcm_role_permissions') || ! Schema::hasColumn('hcm_role_permissions', 'company_id')) {
            return;
        }

        try {
            Schema::table('hcm_role_permissions', function (Blueprint $table): void {
                $table->dropUnique('hcm_role_permissions_tenant_unique');
            });
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'doesn\'t exist') === false && stripos($e->getMessage(), 'can\'t drop') === false) {
                throw $e;
            }
        }

        try {
            Schema::table('hcm_role_permissions', function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
            });
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'doesn\'t exist') === false && stripos($e->getMessage(), 'can\'t drop') === false) {
                throw $e;
            }
        }

        Schema::table('hcm_role_permissions', function (Blueprint $table): void {
            $table->dropIndex('hcm_role_permissions_company_role_idx');
            $table->dropIndex('hcm_role_permissions_company_permission_idx');
            $table->dropIndex('hcm_role_permissions_role_id_idx');
            $table->dropColumn('company_id');
            $table->unique(['role_id', 'permission_id'], 'hcm_role_permissions_unique');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return $rows !== [];
    }

    private function tryAddCompanyIdForeignKey(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $driver = DB::getDriverName();

        // In UUID-cutover MySQL states, companies.id can become non-unique legacy column.
        // On non-MySQL drivers (e.g. sqlite test DB), skip this probe and rely on try/catch below.
        $supportsForeignToId = true;
        if ($driver === 'mysql') {
            $supportsForeignToId = DB::table('information_schema.statistics')
                ->whereRaw('table_schema = database()')
                ->where('table_name', 'companies')
                ->where('column_name', 'id')
                ->where('non_unique', 0)
                ->exists();
        }

        if (! $supportsForeignToId) {
            return;
        }

        try {
            Schema::table('hcm_role_permissions', function (Blueprint $table): void {
                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            });
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false && stripos($e->getMessage(), 'Duplicate foreign key constraint name') === false) {
                throw $e;
            }
        }
    }
};