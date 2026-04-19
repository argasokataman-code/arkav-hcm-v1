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

        // Check if column already exists (might have been added by another migration)
        if (! Schema::hasColumn('hcm_role_permissions', 'company_id')) {
            // Add company_id to hcm_role_permissions for tenant-scoped mappings
            Schema::table('hcm_role_permissions', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->after('permission_id');
                $table->index(['company_id', 'role_id'], 'hcm_role_permissions_company_role_idx');
                $table->index(['company_id', 'permission_id'], 'hcm_role_permissions_company_permission_idx');
            });

            $this->tryAddCompanyIdForeignKey();

            // Update unique constraint to include company_id
            try {
                Schema::table('hcm_role_permissions', function (Blueprint $table): void {
                    $table->dropUnique('hcm_role_permissions_unique');
                });
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'doesn\'t exist') === false && stripos($e->getMessage(), 'can\'t drop') === false) {
                    throw $e;
                }
            }

            try {
                Schema::table('hcm_role_permissions', function (Blueprint $table): void {
                    $table->unique(['company_id', 'role_id', 'permission_id'], 'hcm_role_permissions_tenant_unique');
                });
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                    throw $e;
                }
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
            $table->dropColumn('company_id');
            $table->unique(['role_id', 'permission_id'], 'hcm_role_permissions_unique');
        });
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
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }
};