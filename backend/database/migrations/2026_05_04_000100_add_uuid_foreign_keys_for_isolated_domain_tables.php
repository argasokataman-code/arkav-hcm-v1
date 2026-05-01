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
        if (! $this->isMySqlFamily()) {
            return;
        }

        $this->ensurePolicyUuidsAreUnique();
        $this->ensureUuidColumns();
        $this->backfillRelationUuids();
        $this->normalizeOrphans();
        $this->addIndexes();
        $this->addForeignKeys();
    }

    public function down(): void
    {
        // Forward-only migration.
    }

    private function isMySqlFamily(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function ensurePolicyUuidsAreUnique(): void
    {
        if (! Schema::hasTable('hcm_tax_governance_policies') || ! Schema::hasColumn('hcm_tax_governance_policies', 'uuid')) {
            return;
        }

        DB::table('hcm_tax_governance_policies')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('hcm_tax_governance_policies')
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            }, 'id');

        $duplicates = DB::table('hcm_tax_governance_policies')
            ->select('uuid')
            ->whereNotNull('uuid')
            ->groupBy('uuid')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('uuid');

        foreach ($duplicates as $duplicateUuid) {
            $ids = DB::table('hcm_tax_governance_policies')
                ->where('uuid', $duplicateUuid)
                ->orderBy('id')
                ->pluck('id');

            $skipFirst = true;
            foreach ($ids as $id) {
                if ($skipFirst) {
                    $skipFirst = false;
                    continue;
                }

                DB::table('hcm_tax_governance_policies')
                    ->where('id', $id)
                    ->update(['uuid' => (string) Str::uuid()]);
            }
        }

        $this->safeUnique('hcm_tax_governance_policies', 'uuid', 'hcm_tax_governance_policies_uuid_uq');
    }

    private function ensureUuidColumns(): void
    {
        if (Schema::hasTable('hcm_billing_tax_policies')) {
            Schema::table('hcm_billing_tax_policies', function (Blueprint $table): void {
                if (! Schema::hasColumn('hcm_billing_tax_policies', 'company_uuid')) {
                    $table->uuid('company_uuid')->nullable()->after('company_id');
                }
                if (! Schema::hasColumn('hcm_billing_tax_policies', 'created_by_user_uuid')) {
                    $table->uuid('created_by_user_uuid')->nullable()->after('created_by_user_id');
                }
                if (! Schema::hasColumn('hcm_billing_tax_policies', 'updated_by_user_uuid')) {
                    $table->uuid('updated_by_user_uuid')->nullable()->after('updated_by_user_id');
                }
            });
        }

        if (Schema::hasTable('hcm_tax_governance_projections')) {
            Schema::table('hcm_tax_governance_projections', function (Blueprint $table): void {
                if (! Schema::hasColumn('hcm_tax_governance_projections', 'company_uuid')) {
                    $table->uuid('company_uuid')->nullable()->after('company_id');
                }
                if (! Schema::hasColumn('hcm_tax_governance_projections', 'last_actor_user_uuid')) {
                    $table->uuid('last_actor_user_uuid')->nullable()->after('last_actor_user_id');
                }
            });
        }

        if (Schema::hasTable('hcm_tax_governance_anomalies')) {
            Schema::table('hcm_tax_governance_anomalies', function (Blueprint $table): void {
                if (! Schema::hasColumn('hcm_tax_governance_anomalies', 'company_uuid')) {
                    $table->uuid('company_uuid')->nullable()->after('company_id');
                }
                if (! Schema::hasColumn('hcm_tax_governance_anomalies', 'affected_employee_uuid')) {
                    $table->uuid('affected_employee_uuid')->nullable()->after('affected_employee_id');
                }
                if (! Schema::hasColumn('hcm_tax_governance_anomalies', 'acknowledged_by_user_uuid')) {
                    $table->uuid('acknowledged_by_user_uuid')->nullable()->after('acknowledged_by_user_id');
                }
            });
        }

        if (Schema::hasTable('platform_revenue_transactions')) {
            Schema::table('platform_revenue_transactions', function (Blueprint $table): void {
                if (! Schema::hasColumn('platform_revenue_transactions', 'company_uuid')) {
                    $table->uuid('company_uuid')->nullable()->after('company_id');
                }
            });
        }

        if (Schema::hasTable('platform_monthly_financial_summaries')) {
            Schema::table('platform_monthly_financial_summaries', function (Blueprint $table): void {
                if (! Schema::hasColumn('platform_monthly_financial_summaries', 'locked_by_user_uuid')) {
                    $table->uuid('locked_by_user_uuid')->nullable()->after('locked_by_user_id');
                }
            });
        }
    }

    private function backfillRelationUuids(): void
    {
        $this->syncUuidByJoin('hcm_billing_tax_policies', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_billing_tax_policies', 'created_by_user_id', 'created_by_user_uuid', 'users');
        $this->syncUuidByJoin('hcm_billing_tax_policies', 'updated_by_user_id', 'updated_by_user_uuid', 'users');

        $this->syncUuidByJoin('hcm_tax_governance_projections', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_tax_governance_projections', 'last_actor_user_id', 'last_actor_user_uuid', 'users');

        $this->syncUuidByJoin('hcm_tax_governance_anomalies', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_tax_governance_anomalies', 'affected_employee_id', 'affected_employee_uuid', 'users');
        $this->syncUuidByJoin('hcm_tax_governance_anomalies', 'acknowledged_by_user_id', 'acknowledged_by_user_uuid', 'users');

        $this->syncUuidByJoin('platform_revenue_transactions', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('platform_monthly_financial_summaries', 'locked_by_user_id', 'locked_by_user_uuid', 'users');

        $this->fallbackCompanyUuid('hcm_billing_tax_policies', 'company_uuid');
        $this->fallbackCompanyUuid('hcm_tax_governance_projections', 'company_uuid');
        $this->fallbackCompanyUuid('hcm_tax_governance_anomalies', 'company_uuid');
        $this->fallbackCompanyUuid('platform_revenue_transactions', 'company_uuid');
    }

    private function normalizeOrphans(): void
    {
        // nullable relationships
        $this->nullifyOrphansByUuid('hcm_billing_tax_policies', 'created_by_user_uuid', 'users');
        $this->nullifyOrphansByUuid('hcm_billing_tax_policies', 'updated_by_user_uuid', 'users');

        $this->nullifyOrphansByUuid('hcm_tax_governance_projections', 'last_actor_user_uuid', 'users');
        $this->nullifyOrphansByUuid('hcm_tax_governance_projections', 'policy_uuid', 'hcm_tax_governance_policies');

        $this->nullifyOrphansByUuid('hcm_tax_governance_anomalies', 'affected_employee_uuid', 'users');
        $this->nullifyOrphansByUuid('hcm_tax_governance_anomalies', 'acknowledged_by_user_uuid', 'users');
        $this->nullifyOrphansByUuid('hcm_tax_governance_anomalies', 'affected_policy_id', 'hcm_tax_governance_policies');

        $this->nullifyOrphansByUuid('platform_monthly_financial_summaries', 'locked_by_user_uuid', 'users');
    }

    private function addIndexes(): void
    {
        $this->safeIndex('hcm_billing_tax_policies', 'company_uuid', 'hcm_billing_tax_policies_company_uuid_idx');
        $this->safeIndex('hcm_billing_tax_policies', 'created_by_user_uuid', 'hcm_billing_tax_policies_created_by_uuid_idx');
        $this->safeIndex('hcm_billing_tax_policies', 'updated_by_user_uuid', 'hcm_billing_tax_policies_updated_by_uuid_idx');

        $this->safeIndex('hcm_tax_governance_projections', 'company_uuid', 'hcm_tax_gov_proj_company_uuid_idx');
        $this->safeIndex('hcm_tax_governance_projections', 'last_actor_user_uuid', 'hcm_tax_gov_proj_actor_user_uuid_idx');
        $this->safeIndex('hcm_tax_governance_projections', 'policy_uuid', 'hcm_tax_gov_proj_policy_uuid_idx');

        $this->safeIndex('hcm_tax_governance_anomalies', 'company_uuid', 'hcm_tax_gov_anom_company_uuid_idx');
        $this->safeIndex('hcm_tax_governance_anomalies', 'affected_employee_uuid', 'hcm_tax_gov_anom_emp_uuid_idx');
        $this->safeIndex('hcm_tax_governance_anomalies', 'acknowledged_by_user_uuid', 'hcm_tax_gov_anom_ack_user_uuid_idx');

        $this->safeIndex('platform_revenue_transactions', 'company_uuid', 'platform_rev_tx_company_uuid_idx');
        $this->safeIndex('platform_monthly_financial_summaries', 'locked_by_user_uuid', 'platform_monthly_summary_lock_user_uuid_idx');
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('hcm_billing_tax_policies', 'company_uuid', 'companies', 'uuid', 'hcm_billing_tax_policies_company_uuid_fk', 'null');
        $this->safeForeign('hcm_billing_tax_policies', 'created_by_user_uuid', 'users', 'uuid', 'hcm_billing_tax_policies_created_by_uuid_fk', 'null');
        $this->safeForeign('hcm_billing_tax_policies', 'updated_by_user_uuid', 'users', 'uuid', 'hcm_billing_tax_policies_updated_by_uuid_fk', 'null');

        $this->safeForeign('hcm_tax_governance_projections', 'company_uuid', 'companies', 'uuid', 'hcm_tax_gov_proj_company_uuid_fk', 'null');
        $this->safeForeign('hcm_tax_governance_projections', 'last_actor_user_uuid', 'users', 'uuid', 'hcm_tax_gov_proj_actor_user_uuid_fk', 'null');
        $this->safeForeign('hcm_tax_governance_projections', 'policy_uuid', 'hcm_tax_governance_policies', 'uuid', 'hcm_tax_gov_proj_policy_uuid_fk', 'null');

        $this->safeForeign('hcm_tax_governance_anomalies', 'company_uuid', 'companies', 'uuid', 'hcm_tax_gov_anom_company_uuid_fk', 'null');
        $this->safeForeign('hcm_tax_governance_anomalies', 'affected_employee_uuid', 'users', 'uuid', 'hcm_tax_gov_anom_affected_emp_uuid_fk', 'null');
        $this->safeForeign('hcm_tax_governance_anomalies', 'acknowledged_by_user_uuid', 'users', 'uuid', 'hcm_tax_gov_anom_ack_user_uuid_fk', 'null');
        $this->safeForeign('hcm_tax_governance_anomalies', 'affected_policy_id', 'hcm_tax_governance_policies', 'uuid', 'hcm_tax_gov_anom_affected_policy_uuid_fk', 'null');

        $this->safeForeign('platform_revenue_transactions', 'company_uuid', 'companies', 'uuid', 'platform_rev_tx_company_uuid_fk', 'null');
        $this->safeForeign('platform_monthly_financial_summaries', 'locked_by_user_uuid', 'users', 'uuid', 'platform_monthly_summary_lock_user_uuid_fk', 'null');
    }

    private function syncUuidByJoin(string $table, string $legacyColumn, string $uuidColumn, string $parentTable): void
    {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $legacyColumn)
            || ! Schema::hasColumn($table, $uuidColumn)
            || ! Schema::hasTable($parentTable)
            || ! Schema::hasColumn($parentTable, 'id')
            || ! Schema::hasColumn($parentTable, 'uuid')) {
            return;
        }

        DB::statement("UPDATE {$table} t JOIN {$parentTable} p ON t.{$legacyColumn} = p.id SET t.{$uuidColumn} = p.uuid WHERE t.{$legacyColumn} IS NOT NULL AND t.{$uuidColumn} IS NULL");
    }

    private function fallbackCompanyUuid(string $table, string $companyUuidColumn): void
    {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'company_id')
            || ! Schema::hasColumn($table, $companyUuidColumn)
            || ! Schema::hasTable('companies')
            || ! Schema::hasColumn('companies', 'uuid')) {
            return;
        }

        $defaultCompanyUuid = DB::table('companies')->where('code', 'default_company')->value('uuid')
            ?? DB::table('companies')->orderBy('created_at')->value('uuid');

        if (! $defaultCompanyUuid) {
            return;
        }

        DB::table($table)
            ->whereNotNull('company_id')
            ->whereNull($companyUuidColumn)
            ->update([$companyUuidColumn => $defaultCompanyUuid]);
    }

    private function nullifyOrphansByUuid(string $table, string $column, string $parentTable, string $parentUuidColumn = 'uuid'): void
    {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
            || ! Schema::hasTable($parentTable)
            || ! Schema::hasColumn($parentTable, $parentUuidColumn)) {
            return;
        }

        DB::statement("UPDATE {$table} t LEFT JOIN {$parentTable} p ON t.{$column} = p.{$parentUuidColumn} SET t.{$column} = NULL WHERE t.{$column} IS NOT NULL AND p.{$parentUuidColumn} IS NULL");
    }

    private function safeIndex(string $table, string $column, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $name): void {
                $blueprint->index($column, $name);
            });
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
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
            Schema::table($table, function (Blueprint $blueprint) use ($column, $name): void {
                $blueprint->unique($column, $name);
            });
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }

    private function safeForeign(string $table, string $column, string $parentTable, string $parentColumn, string $name, string $onDelete): void
    {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
            || ! Schema::hasTable($parentTable)
            || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        if ($this->foreignExists($table, $name)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $parentTable, $parentColumn, $name, $onDelete): void {
                $foreign = $blueprint->foreign($column, $name)->references($parentColumn)->on($parentTable);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } elseif ($onDelete === 'restrict') {
                    $foreign->restrictOnDelete();
                } else {
                    $foreign->nullOnDelete();
                }
            });
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }

    private function foreignExists(string $table, string $constraintName): bool
    {
        if (! $this->isMySqlFamily()) {
            return false;
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
