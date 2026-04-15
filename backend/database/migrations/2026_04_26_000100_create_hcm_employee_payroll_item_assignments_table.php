<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hcm_employee_payroll_item_assignments')) {
            $this->ensureIndexesAndForeignKeys();

            return;
        }

        Schema::create('hcm_employee_payroll_item_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('hcm_payroll_item_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'hcm_epia_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('hcm_payroll_item_id', 'hcm_epia_item_fk')
                ->references('id')
                ->on('hcm_payroll_items')
                ->cascadeOnDelete();

            $table->index(['user_id', 'is_active'], 'hcm_epia_user_active_index');
            $table->index(['hcm_payroll_item_id', 'is_active'], 'hcm_epia_item_active_index');
            $table->index(['effective_start_date', 'effective_end_date'], 'hcm_epia_effective_range_index');
        });
    }

    private function ensureIndexesAndForeignKeys(): void
    {
        $tableName = 'hcm_employee_payroll_item_assignments';

        if (! $this->indexExists($tableName, 'hcm_epia_user_active_index')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index(['user_id', 'is_active'], 'hcm_epia_user_active_index');
            });
        }

        if (! $this->indexExists($tableName, 'hcm_epia_item_active_index')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index(['hcm_payroll_item_id', 'is_active'], 'hcm_epia_item_active_index');
            });
        }

        if (! $this->indexExists($tableName, 'hcm_epia_effective_range_index')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index(['effective_start_date', 'effective_end_date'], 'hcm_epia_effective_range_index');
            });
        }

        if (! $this->foreignKeyExists($tableName, 'hcm_epia_user_fk')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('user_id', 'hcm_epia_user_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->foreignKeyExists($tableName, 'hcm_epia_item_fk')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('hcm_payroll_item_id', 'hcm_epia_item_fk')
                    ->references('id')
                    ->on('hcm_payroll_items')
                    ->cascadeOnDelete();
            });
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $schema = (string) DB::getDatabaseName();
        $row = DB::selectOne(
            'select index_name from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$schema, $tableName, $indexName],
        );

        return $row !== null;
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        $schema = (string) DB::getDatabaseName();
        $row = DB::selectOne(
            "select constraint_name from information_schema.table_constraints where table_schema = ? and table_name = ? and constraint_type = 'FOREIGN KEY' and constraint_name = ? limit 1",
            [$schema, $tableName, $constraintName],
        );

        return $row !== null;
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_employee_payroll_item_assignments');
    }
};
