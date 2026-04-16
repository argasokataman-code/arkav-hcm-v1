<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\CompanyMembership;
use Illuminate\Database\Eloquent\Builder;

return new class extends Migration
{
    /**
     * Run the migrations.
     * CRITICAL SECURITY FIX: Add company_id to HCM personnel lifecycle tables to enable tenant isolation.
     * Without company_id, HCM admins from Company A could view/modify data from Company B.
     */
    public function up(): void
    {
        // Add company_id to hcm_promotions
        if (Schema::hasTable('hcm_promotions') && !Schema::hasColumn('hcm_promotions', 'company_id')) {
            Schema::table('hcm_promotions', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->index('company_id');
                $table->index(['company_id', 'user_id']);
            });

            // Backfill company_id from user's company membership
            $this->backfillCompanyIdForPromotion();
        }

        // Add company_id to hcm_resignations
        if (Schema::hasTable('hcm_resignations') && !Schema::hasColumn('hcm_resignations', 'company_id')) {
            Schema::table('hcm_resignations', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->index('company_id');
                $table->index(['company_id', 'user_id']);
            });

            // Backfill company_id from user's company membership
            $this->backfillCompanyIdForResignation();
        }

        // Add company_id to hcm_terminations
        if (Schema::hasTable('hcm_terminations') && !Schema::hasColumn('hcm_terminations', 'company_id')) {
            Schema::table('hcm_terminations', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->index('company_id');
                $table->index(['company_id', 'user_id']);
            });

            // Backfill company_id from user's company membership
            $this->backfillCompanyIdForTermination();
        }
    }

    public function down(): void
    {
        // Drop foreign keys first
        if (Schema::hasTable('hcm_promotions') && Schema::hasColumn('hcm_promotions', 'company_id')) {
            Schema::table('hcm_promotions', function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
                $table->dropIndex(['company_id']);
                $table->dropIndex(['company_id', 'user_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasTable('hcm_resignations') && Schema::hasColumn('hcm_resignations', 'company_id')) {
            Schema::table('hcm_resignations', function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
                $table->dropIndex(['company_id']);
                $table->dropIndex(['company_id', 'user_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasTable('hcm_terminations') && Schema::hasColumn('hcm_terminations', 'company_id')) {
            Schema::table('hcm_terminations', function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
                $table->dropIndex(['company_id']);
                $table->dropIndex(['company_id', 'user_id']);
                $table->dropColumn('company_id');
            });
        }
    }

    /**
     * Backfill company_id for existing hcm_promotions records.
     * Uses user's active company membership or falls back to first membership.
     */
    private function backfillCompanyIdForPromotion(): void
    {
        \DB::table('hcm_promotions as hp')
            ->whereNull('hp.company_id')
            ->join('users as u', 'u.id', '=', 'hp.user_id')
            ->join('company_users as cm', function ($join) {
                $join->on('cm.user_id', '=', 'u.id')
                    ->where('cm.status', 'active');
            })
            ->update([
                'hp.company_id' => \DB::raw('cm.company_id'),
            ]);

        // For records with no active membership, use any membership
        \DB::table('hcm_promotions as hp')
            ->whereNull('hp.company_id')
            ->join('users as u', 'u.id', '=', 'hp.user_id')
            ->join('company_users as cm', function ($join) {
                $join->on('cm.user_id', '=', 'u.id');
            })
            ->update([
                'hp.company_id' => \DB::raw('cm.company_id'),
            ]);
    }

    /**
     * Backfill company_id for existing hcm_resignations records.
     */
    private function backfillCompanyIdForResignation(): void
    {
        \DB::table('hcm_resignations as hr')
            ->whereNull('hr.company_id')
            ->join('users as u', 'u.id', '=', 'hr.user_id')
            ->join('company_users as cm', function ($join) {
                $join->on('cm.user_id', '=', 'u.id')
                    ->where('cm.status', 'active');
            })
            ->update([
                'hr.company_id' => \DB::raw('cm.company_id'),
            ]);

        \DB::table('hcm_resignations as hr')
            ->whereNull('hr.company_id')
            ->join('users as u', 'u.id', '=', 'hr.user_id')
            ->join('company_users as cm', function ($join) {
                $join->on('cm.user_id', '=', 'u.id');
            })
            ->update([
                'hr.company_id' => \DB::raw('cm.company_id'),
            ]);
    }

    /**
     * Backfill company_id for existing hcm_terminations records.
     */
    private function backfillCompanyIdForTermination(): void
    {
        \DB::table('hcm_terminations as ht')
            ->whereNull('ht.company_id')
            ->join('users as u', 'u.id', '=', 'ht.user_id')
            ->join('company_users as cm', function ($join) {
                $join->on('cm.user_id', '=', 'u.id')
                    ->where('cm.status', 'active');
            })
            ->update([
                'ht.company_id' => \DB::raw('cm.company_id'),
            ]);

        \DB::table('hcm_terminations as ht')
            ->whereNull('ht.company_id')
            ->join('users as u', 'u.id', '=', 'ht.user_id')
            ->join('company_users as cm', function ($join) {
                $join->on('cm.user_id', '=', 'u.id');
            })
            ->update([
                'ht.company_id' => \DB::raw('cm.company_id'),
            ]);
    }
};
