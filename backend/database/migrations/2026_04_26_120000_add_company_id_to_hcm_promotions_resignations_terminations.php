<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        if (Schema::hasTable('hcm_promotions') && ! Schema::hasColumn('hcm_promotions', 'company_id')) {
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
        if (Schema::hasTable('hcm_resignations') && ! Schema::hasColumn('hcm_resignations', 'company_id')) {
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
        if (Schema::hasTable('hcm_terminations') && ! Schema::hasColumn('hcm_terminations', 'company_id')) {
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
        $this->backfillTableCompanyId('hcm_promotions');
    }

    /**
     * Backfill company_id for existing hcm_resignations records.
     */
    private function backfillCompanyIdForResignation(): void
    {
        $this->backfillTableCompanyId('hcm_resignations');
    }

    /**
     * Backfill company_id for existing hcm_terminations records.
     */
    private function backfillCompanyIdForTermination(): void
    {
        $this->backfillTableCompanyId('hcm_terminations');
    }

    /**
     * Backfill company_id in a cross-database-safe way (no joined update).
     */
    private function backfillTableCompanyId(string $table): void
    {
        DB::table($table)
            ->select('id', 'user_id')
            ->whereNull('company_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $companyId = $this->resolveCompanyIdForUser((int) $row->user_id);
                    if (! $companyId) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', (int) $row->id)
                        ->whereNull('company_id')
                        ->update(['company_id' => $companyId]);
                }
            });
    }

    private function resolveCompanyIdForUser(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        $activeCompanyId = DB::table('company_users')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('company_id')
            ->value('company_id');

        if (is_numeric($activeCompanyId)) {
            return (int) $activeCompanyId;
        }

        $anyCompanyId = DB::table('company_users')
            ->where('user_id', $userId)
            ->orderBy('company_id')
            ->value('company_id');

        return is_numeric($anyCompanyId) ? (int) $anyCompanyId : null;
    }
};
