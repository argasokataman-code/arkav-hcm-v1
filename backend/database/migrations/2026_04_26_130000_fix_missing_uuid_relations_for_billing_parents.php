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
        $this->ensureParentUuidColumns();
        $this->backfillParentUuids();
        $this->backfillChildUuidColumns();
        $this->addIndexesAndConstraints();
    }

    public function down(): void
    {
        // Forward-only recovery migration.
    }

    private function ensureParentUuidColumns(): void
    {
        if (Schema::hasTable('companies') && ! Schema::hasColumn('companies', 'uuid')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        if (Schema::hasTable('packages') && ! Schema::hasColumn('packages', 'uuid')) {
            Schema::table('packages', function (Blueprint $table): void {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }
    }

    private function backfillParentUuids(): void
    {
        $this->backfillRowUuid('companies');
        $this->backfillRowUuid('packages');

        $this->safeUnique('companies', 'uuid', 'companies_uuid_unique');
        $this->safeUnique('packages', 'uuid', 'packages_uuid_unique');
    }

    private function backfillRowUuid(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uuid')) {
            return;
        }

        DB::table($table)
            ->whereNull('uuid')
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            }, 'id');
    }

    private function backfillChildUuidColumns(): void
    {
        $this->updateFromTable('subscriptions', 'company_id', 'company_uuid', 'companies');
        $this->updateFromTable('subscriptions', 'package_id', 'package_uuid', 'packages');

        $this->updateFromTable('purchase_transactions', 'company_id', 'company_uuid', 'companies');
        $this->updateFromTable('invoices', 'company_id', 'company_uuid', 'companies');
        $this->updateFromTable('payments', 'company_id', 'company_uuid', 'companies');
    }

    private function addIndexesAndConstraints(): void
    {
        $this->safeIndex('subscriptions', 'company_uuid', 'subscriptions_company_uuid_idx');
        $this->safeIndex('subscriptions', 'package_uuid', 'subscriptions_package_uuid_idx');
        $this->safeIndex('purchase_transactions', 'company_uuid', 'purchase_transactions_company_uuid_idx');
        $this->safeIndex('invoices', 'company_uuid', 'invoices_company_uuid_idx');
        $this->safeIndex('payments', 'company_uuid', 'payments_company_uuid_idx');

        $this->safeForeign('subscriptions', 'company_uuid', 'companies', 'uuid', 'subscriptions_company_uuid_fk', 'cascade');
        $this->safeForeign('subscriptions', 'package_uuid', 'packages', 'uuid', 'subscriptions_package_uuid_fk', 'restrict');
        $this->safeForeign('purchase_transactions', 'company_uuid', 'companies', 'uuid', 'purchase_transactions_company_uuid_fk', 'cascade');
        $this->safeForeign('invoices', 'company_uuid', 'companies', 'uuid', 'invoices_company_uuid_fk', 'cascade');
        $this->safeForeign('payments', 'company_uuid', 'companies', 'uuid', 'payments_company_uuid_fk', 'cascade');
    }

    private function updateFromTable(string $table, string $legacyIdColumn, string $uuidColumn, string $parentTable): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, $legacyIdColumn) || ! Schema::hasColumn($table, $uuidColumn)) {
            return;
        }

        if (! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, 'uuid')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE {$table} t JOIN {$parentTable} p ON t.{$legacyIdColumn} = p.id SET t.{$uuidColumn} = p.uuid WHERE t.{$legacyIdColumn} IS NOT NULL AND t.{$uuidColumn} IS NULL");
            return;
        }

        $rows = DB::table($table)
            ->whereNotNull($legacyIdColumn)
            ->whereNull($uuidColumn)
            ->select('id', $legacyIdColumn)
            ->get();

        foreach ($rows as $row) {
            $uuid = DB::table($parentTable)->where('id', $row->{$legacyIdColumn})->value('uuid');
            DB::table($table)->where('id', $row->id)->update([$uuidColumn => $uuid]);
        }
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
                } elseif ($onDelete === 'restrict') {
                    $foreign->restrictOnDelete();
                }
            });
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }
};
