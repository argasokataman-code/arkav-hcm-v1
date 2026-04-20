<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_payroll_runs')) {
            return;
        }

        Schema::table('hcm_payroll_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('hcm_payroll_runs', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('finalized_at');
            }

            if (! Schema::hasColumn('hcm_payroll_runs', 'voided_by_user_id')) {
                $table->unsignedBigInteger('voided_by_user_id')->nullable()->after('finalized_by_user_uuid');
            }

            if (! Schema::hasColumn('hcm_payroll_runs', 'voided_by_user_uuid')) {
                $table->uuid('voided_by_user_uuid')->nullable()->after('voided_by_user_id');
            }
        });

        if (Schema::hasColumn('hcm_payroll_runs', 'voided_by_user_uuid')) {
            try {
                Schema::table('hcm_payroll_runs', function (Blueprint $table): void {
                    $table->index('voided_by_user_uuid', 'hcm_payroll_runs_voided_by_user_uuid_idx');
                });
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                    throw $e;
                }
            }

            try {
                Schema::table('hcm_payroll_runs', function (Blueprint $table): void {
                    $table->foreign('voided_by_user_uuid', 'hcm_payroll_runs_voided_by_user_uuid_fk')
                        ->references('uuid')
                        ->on('users')
                        ->nullOnDelete();
                });
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                    throw $e;
                }
            }
        }

        if (Schema::hasColumn('hcm_payroll_runs', 'status') && Schema::hasColumn('hcm_payroll_runs', 'voided_at')) {
            DB::table('hcm_payroll_runs')
                ->where('status', 'void')
                ->whereNull('voided_at')
                ->update(['voided_at' => DB::raw('COALESCE(updated_at, finalized_at, calculated_at, created_at)')]);
        }

        if (
            Schema::hasColumn('hcm_payroll_runs', 'voided_by_user_id')
            && Schema::hasColumn('hcm_payroll_runs', 'voided_by_user_uuid')
            && Schema::hasTable('users')
            && Schema::hasColumn('users', 'uuid')
        ) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('UPDATE hcm_payroll_runs r JOIN users u ON r.voided_by_user_id = u.id SET r.voided_by_user_uuid = u.uuid WHERE r.voided_by_user_id IS NOT NULL AND r.voided_by_user_uuid IS NULL');
            } else {
                $rows = DB::table('hcm_payroll_runs')
                    ->whereNotNull('voided_by_user_id')
                    ->whereNull('voided_by_user_uuid')
                    ->select('id', 'voided_by_user_id')
                    ->get();

                foreach ($rows as $row) {
                    $uuid = DB::table('users')->where('id', $row->voided_by_user_id)->value('uuid');
                    DB::table('hcm_payroll_runs')->where('id', $row->id)->update(['voided_by_user_uuid' => $uuid]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('hcm_payroll_runs')) {
            return;
        }

        Schema::table('hcm_payroll_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('hcm_payroll_runs', 'voided_by_user_uuid')) {
                try {
                    $table->dropForeign('hcm_payroll_runs_voided_by_user_uuid_fk');
                } catch (\Throwable) {
                }
                try {
                    $table->dropIndex('hcm_payroll_runs_voided_by_user_uuid_idx');
                } catch (\Throwable) {
                }
                $table->dropColumn('voided_by_user_uuid');
            }

            if (Schema::hasColumn('hcm_payroll_runs', 'voided_by_user_id')) {
                $table->dropColumn('voided_by_user_id');
            }

            if (Schema::hasColumn('hcm_payroll_runs', 'voided_at')) {
                $table->dropColumn('voided_at');
            }
        });
    }
};