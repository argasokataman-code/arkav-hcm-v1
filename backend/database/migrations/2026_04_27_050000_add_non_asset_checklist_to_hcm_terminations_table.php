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
            if (! Schema::hasColumn('hcm_terminations', 'non_asset_checklist')) {
                $table->json('non_asset_checklist')->nullable()->after('clearance_items');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hcm_terminations')) {
            return;
        }

        Schema::table('hcm_terminations', function (Blueprint $table): void {
            if (Schema::hasColumn('hcm_terminations', 'non_asset_checklist')) {
                $table->dropColumn('non_asset_checklist');
            }
        });
    }
};
