<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_requests') && ! Schema::hasColumn('leave_requests', 'approved_by_user_id')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->after('status');
                $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
                $table->index('approved_by_user_id', 'leave_requests_approved_by_user_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_requests') && Schema::hasColumn('leave_requests', 'approved_by_user_id')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->dropIndex('leave_requests_approved_by_user_id_idx');
                $table->dropColumn(['approved_by_user_id', 'approved_at']);
            });
        }
    }
};
