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
        if (Schema::hasTable('hcm_tax_governance_anomalies')) {
            Schema::table('hcm_tax_governance_anomalies', function (Blueprint $table) {
                // Add acknowledgment fields if they don't exist
                if (!Schema::hasColumn('hcm_tax_governance_anomalies', 'acknowledged_by_user_id')) {
                    $table->unsignedBigInteger('acknowledged_by_user_id')->nullable()->after('resolution_note');
                }
                if (!Schema::hasColumn('hcm_tax_governance_anomalies', 'acknowledged_at')) {
                    $table->timestamp('acknowledged_at')->nullable()->after('acknowledged_by_user_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('hcm_tax_governance_anomalies')) {
            Schema::table('hcm_tax_governance_anomalies', function (Blueprint $table) {
                if (Schema::hasColumn('hcm_tax_governance_anomalies', 'acknowledged_by_user_id')) {
                    $table->dropColumn('acknowledged_by_user_id');
                }
                if (Schema::hasColumn('hcm_tax_governance_anomalies', 'acknowledged_at')) {
                    $table->dropColumn('acknowledged_at');
                }
            });
        }
    }
};
