<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('overtime_requests')) {
            return;
        }

        Schema::table('overtime_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('overtime_requests', 'day_type')) {
                $table->string('day_type', 40)->nullable()->after('minutes');
            }

            if (! Schema::hasColumn('overtime_requests', 'weekly_work_days')) {
                $table->unsignedTinyInteger('weekly_work_days')->nullable()->after('day_type');
            }

            $table->index(['work_date', 'status'], 'overtime_requests_work_date_status_idx');
            $table->index(['company_id', 'user_id', 'work_date'], 'overtime_requests_company_user_work_date_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('overtime_requests')) {
            return;
        }

        Schema::table('overtime_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('overtime_requests', 'day_type')) {
                $table->dropColumn('day_type');
            }

            if (Schema::hasColumn('overtime_requests', 'weekly_work_days')) {
                $table->dropColumn('weekly_work_days');
            }

            $table->dropIndex('overtime_requests_work_date_status_idx');
            $table->dropIndex('overtime_requests_company_user_work_date_idx');
        });
    }
};
