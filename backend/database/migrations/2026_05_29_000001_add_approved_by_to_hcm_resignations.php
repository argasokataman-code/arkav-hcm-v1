<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hcm_resignations') && ! Schema::hasColumn('hcm_resignations', 'approved_by_user_id')) {
            Schema::table('hcm_resignations', function (Blueprint $table): void {
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->after('status');
                $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hcm_resignations') && Schema::hasColumn('hcm_resignations', 'approved_by_user_id')) {
            Schema::table('hcm_resignations', function (Blueprint $table): void {
                $table->dropColumn(['approved_by_user_id', 'approved_at']);
            });
        }
    }
};
