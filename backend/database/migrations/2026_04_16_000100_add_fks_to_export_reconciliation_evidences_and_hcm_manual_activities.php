<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if companies table exists before adding foreign keys
        if (!Schema::hasTable('companies')) {
            return;
        }

        Schema::table('export_reconciliation_evidences', function (Blueprint $table): void {
            // Add foreign keys if columns exist and constraints not present yet.
            if (Schema::hasColumn('export_reconciliation_evidences', 'company_id')) {
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();
            }

            if (Schema::hasColumn('export_reconciliation_evidences', 'exported_by_user_id')) {
                $table->foreign('exported_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });

        Schema::table('hcm_manual_activities', function (Blueprint $table): void {
            if (Schema::hasColumn('hcm_manual_activities', 'company_id')) {
                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();
            }

            if (Schema::hasColumn('hcm_manual_activities', 'created_by_user_id')) {
                $table->foreign('created_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (Schema::hasColumn('hcm_manual_activities', 'updated_by_user_id')) {
                $table->foreign('updated_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // Check if companies table exists before dropping foreign keys
        if (!Schema::hasTable('companies')) {
            return;
        }

        Schema::table('export_reconciliation_evidences', function (Blueprint $table): void {
            // Drop by convention-based names, but ignore errors if they don't exist
            try {
                $table->dropForeign(['company_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
            try {
                $table->dropForeign(['exported_by_user_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
        });

        Schema::table('hcm_manual_activities', function (Blueprint $table): void {
            try {
                $table->dropForeign(['company_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
            try {
                $table->dropForeign(['created_by_user_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
            try {
                $table->dropForeign(['updated_by_user_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
        });
    }
};

