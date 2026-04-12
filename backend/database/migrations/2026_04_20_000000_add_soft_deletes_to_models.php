<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add soft deletes to policies table
        if (Schema::hasTable('policies') && !Schema::hasColumn('policies', 'deleted_at')) {
            Schema::table('policies', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        // Add soft deletes to departments table
        if (Schema::hasTable('departments') && !Schema::hasColumn('departments', 'deleted_at')) {
            Schema::table('departments', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        // Add soft deletes to hcm_resignations table
        if (Schema::hasTable('hcm_resignations') && !Schema::hasColumn('hcm_resignations', 'deleted_at')) {
            Schema::table('hcm_resignations', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        // Add soft deletes to hcm_terminations table
        if (Schema::hasTable('hcm_terminations') && !Schema::hasColumn('hcm_terminations', 'deleted_at')) {
            Schema::table('hcm_terminations', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop soft deletes from policies table
        if (Schema::hasTable('policies') && Schema::hasColumn('policies', 'deleted_at')) {
            Schema::table('policies', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }

        // Drop soft deletes from departments table
        if (Schema::hasTable('departments') && Schema::hasColumn('departments', 'deleted_at')) {
            Schema::table('departments', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }

        // Drop soft deletes from hcm_resignations table
        if (Schema::hasTable('hcm_resignations') && Schema::hasColumn('hcm_resignations', 'deleted_at')) {
            Schema::table('hcm_resignations', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }

        // Drop soft deletes from hcm_terminations table
        if (Schema::hasTable('hcm_terminations') && Schema::hasColumn('hcm_terminations', 'deleted_at')) {
            Schema::table('hcm_terminations', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
