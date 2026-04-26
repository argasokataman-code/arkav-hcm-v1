<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_terminations')) {
            return;
        }

        Schema::table('hcm_terminations', function (Blueprint $table): void {
            if (! Schema::hasColumn('hcm_terminations', 'workflow_stage')) {
                $table->string('workflow_stage', 64)->nullable()->after('policy_formula_version');
            }
            if (! Schema::hasColumn('hcm_terminations', 'workflow_reviewed_by_user_id')) {
                $table->unsignedBigInteger('workflow_reviewed_by_user_id')->nullable()->after('workflow_stage');
            }
            if (! Schema::hasColumn('hcm_terminations', 'workflow_reviewed_at')) {
                $table->timestamp('workflow_reviewed_at')->nullable()->after('workflow_reviewed_by_user_id');
            }
            if (! Schema::hasColumn('hcm_terminations', 'workflow_approved_by_user_id')) {
                $table->unsignedBigInteger('workflow_approved_by_user_id')->nullable()->after('workflow_reviewed_at');
            }
            if (! Schema::hasColumn('hcm_terminations', 'workflow_approved_at')) {
                $table->timestamp('workflow_approved_at')->nullable()->after('workflow_approved_by_user_id');
            }
            if (! Schema::hasColumn('hcm_terminations', 'workflow_finalized_by_user_id')) {
                $table->unsignedBigInteger('workflow_finalized_by_user_id')->nullable()->after('workflow_approved_at');
            }
            if (! Schema::hasColumn('hcm_terminations', 'workflow_finalized_at')) {
                $table->timestamp('workflow_finalized_at')->nullable()->after('workflow_finalized_by_user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hcm_terminations')) {
            return;
        }

        Schema::table('hcm_terminations', function (Blueprint $table): void {
            foreach ([
                'workflow_finalized_at',
                'workflow_finalized_by_user_id',
                'workflow_approved_at',
                'workflow_approved_by_user_id',
                'workflow_reviewed_at',
                'workflow_reviewed_by_user_id',
                'workflow_stage',
            ] as $column) {
                if (Schema::hasColumn('hcm_terminations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
