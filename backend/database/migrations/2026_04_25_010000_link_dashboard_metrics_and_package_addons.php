<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('dashboard_metrics') && ! Schema::hasColumn('dashboard_metrics', 'company_id')) {
            Schema::table('dashboard_metrics', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->after('metric_key');
                $table->index('company_id', 'dashboard_metrics_company_id_index');
            });
        }

        if (
            Schema::hasTable('dashboard_metrics')
            && Schema::hasColumn('dashboard_metrics', 'company_id')
            && ! $this->constraintExists('dashboard_metrics', 'dashboard_metrics_company_id_foreign')
        ) {
            Schema::table('dashboard_metrics', function (Blueprint $table): void {
                $table->foreign('company_id', 'dashboard_metrics_company_id_foreign')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('purchase_transactions') && ! Schema::hasColumn('purchase_transactions', 'package_addon_id')) {
            Schema::table('purchase_transactions', function (Blueprint $table): void {
                $table->unsignedBigInteger('package_addon_id')->nullable()->after('subscription_id');
                $table->index('package_addon_id', 'purchase_transactions_package_addon_id_index');
            });
        }

        if (
            Schema::hasTable('purchase_transactions')
            && Schema::hasColumn('purchase_transactions', 'package_addon_id')
            && ! $this->constraintExists('purchase_transactions', 'purchase_transactions_package_addon_id_foreign')
        ) {
            Schema::table('purchase_transactions', function (Blueprint $table): void {
                $table->foreign('package_addon_id', 'purchase_transactions_package_addon_id_foreign')
                    ->references('id')
                    ->on('package_addons')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (
            Schema::hasTable('purchase_transactions')
            && $this->constraintExists('purchase_transactions', 'purchase_transactions_package_addon_id_foreign')
        ) {
            Schema::table('purchase_transactions', function (Blueprint $table): void {
                $table->dropForeign('purchase_transactions_package_addon_id_foreign');
            });
        }

        if (Schema::hasTable('purchase_transactions') && Schema::hasColumn('purchase_transactions', 'package_addon_id')) {
            Schema::table('purchase_transactions', function (Blueprint $table): void {
                $table->dropColumn('package_addon_id');
            });
        }

        if (
            Schema::hasTable('dashboard_metrics')
            && $this->constraintExists('dashboard_metrics', 'dashboard_metrics_company_id_foreign')
        ) {
            Schema::table('dashboard_metrics', function (Blueprint $table): void {
                $table->dropForeign('dashboard_metrics_company_id_foreign');
            });
        }

        if (Schema::hasTable('dashboard_metrics') && Schema::hasColumn('dashboard_metrics', 'company_id')) {
            Schema::table('dashboard_metrics', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        $database = DB::getDatabaseName();

        $rows = DB::select(
            'SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? LIMIT 1',
            [$database, $table, $constraint]
        );

        return count($rows) > 0;
    }
};
