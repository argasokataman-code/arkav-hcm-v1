<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_training_types', function (Blueprint $table): void {
            if (! Schema::hasColumn('hcm_training_types', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index('company_id');
            }
        });

        Schema::table('hcm_trainers', function (Blueprint $table): void {
            if (! Schema::hasColumn('hcm_trainers', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index('company_id');
            }
        });

        Schema::table('hcm_trainings', function (Blueprint $table): void {
            if (! Schema::hasColumn('hcm_trainings', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index('company_id');
            }
        });

        if (Schema::hasTable('companies')) {
            $fallbackCompanyId = DB::table('companies')->orderBy('id')->value('id');

            if ($fallbackCompanyId) {
                DB::table('hcm_training_types')->whereNull('company_id')->update(['company_id' => $fallbackCompanyId]);
                DB::table('hcm_trainers')->whereNull('company_id')->update(['company_id' => $fallbackCompanyId]);
                DB::table('hcm_trainings')->whereNull('company_id')->update(['company_id' => $fallbackCompanyId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('hcm_trainings', function (Blueprint $table): void {
            if (Schema::hasColumn('hcm_trainings', 'company_id')) {
                $table->dropIndex(['company_id']);
                $table->dropColumn('company_id');
            }
        });

        Schema::table('hcm_trainers', function (Blueprint $table): void {
            if (Schema::hasColumn('hcm_trainers', 'company_id')) {
                $table->dropIndex(['company_id']);
                $table->dropColumn('company_id');
            }
        });

        Schema::table('hcm_training_types', function (Blueprint $table): void {
            if (Schema::hasColumn('hcm_training_types', 'company_id')) {
                $table->dropIndex(['company_id']);
                $table->dropColumn('company_id');
            }
        });
    }
};