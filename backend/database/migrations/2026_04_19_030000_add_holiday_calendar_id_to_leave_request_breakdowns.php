<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if column already exists to handle idempotency
        if (!Schema::hasColumn('leave_request_breakdowns', 'holiday_calendar_id')) {
            Schema::table('leave_request_breakdowns', function (Blueprint $table): void {
                $table->unsignedBigInteger('holiday_calendar_id')
                    ->nullable()
                    ->after('holiday_name')
                    ->comment('FK ke holiday_calendars.id — diisi saat is_holiday=true');

                $table->foreign('holiday_calendar_id')
                    ->references('id')
                    ->on('holiday_calendars')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('leave_request_breakdowns', function (Blueprint $table): void {
            $table->dropForeign(['holiday_calendar_id']);
            $table->dropColumn('holiday_calendar_id');
        });
    }
};
